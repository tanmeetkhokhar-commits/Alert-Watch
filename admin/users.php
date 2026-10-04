<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

$pageTitle = 'User Management';

$error = '';
$success = '';

$currentAdminId = currentUserId();



if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please refresh the page and try again.';
    } else {

        $action = $_POST['action'] ?? '';


        if ($action === 'add') {

            $name = trim($_POST['name'] ?? '');
            $email = strtolower(trim($_POST['email'] ?? ''));
            $password = $_POST['password'] ?? '';
            $role = $_POST['role'] ?? 'user';
            $status = $_POST['status'] ?? 'active';


            if ($name === '') {
                $error = 'Name is required.';
            } elseif ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Please enter a valid email address.';
            } elseif (strlen($password) < 6) {
                $error = 'Password must contain at least 6 characters.';
            } elseif (!in_array($role, ['admin', 'user'], true)) {
                $error = 'Invalid user role.';
            } elseif (!in_array($status, ['active', 'inactive'], true)) {
                $error = 'Invalid user status.';
            } else {

                $check = $pdo->prepare("
                    SELECT id
                    FROM users
                    WHERE email = :email
                    LIMIT 1
                ");

                $check->execute([
                    'email' => $email
                ]);

                if ($check->fetch()) {

                    $error = 'A user with this email address already exists.';

                } else {

                    $stmt = $pdo->prepare("
                        INSERT INTO users
                        (
                            name,
                            email,
                            password,
                            role,
                            status,
                            created_at,
                            updated_at
                        )
                        VALUES
                        (
                            :name,
                            :email,
                            :password,
                            :role,
                            :status,
                            NOW(),
                            NOW()
                        )
                    ");

                    $stmt->execute([
                        'name' => $name,
                        'email' => $email,
                        'password' => password_hash(
                            $password,
                            PASSWORD_DEFAULT
                        ),
                        'role' => $role,
                        'status' => $status
                    ]);

                    $success = 'User created successfully.';
                }
            }
        }


       

        elseif ($action === 'edit') {

            $userId = filter_var(
                $_POST['user_id'] ?? '',
                FILTER_VALIDATE_INT
            );

            $name = trim($_POST['name'] ?? '');
            $email = strtolower(trim($_POST['email'] ?? ''));
            $password = $_POST['password'] ?? '';
            $role = $_POST['role'] ?? 'user';
            $status = $_POST['status'] ?? 'active';


            if (!$userId) {

                $error = 'Invalid user selected.';

            } elseif ($name === '') {

                $error = 'Name is required.';

            } elseif ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {

                $error = 'Please enter a valid email address.';

            } elseif (!in_array($role, ['admin', 'user'], true)) {

                $error = 'Invalid user role.';

            } elseif (!in_array($status, ['active', 'inactive'], true)) {

                $error = 'Invalid user status.';

            } else {

               
                $check = $pdo->prepare("
                    SELECT id
                    FROM users
                    WHERE email = :email
                      AND id <> :id
                    LIMIT 1
                ");

                $check->execute([
                    'email' => $email,
                    'id' => $userId
                ]);

                if ($check->fetch()) {

                    $error = 'Another user already uses this email address.';

                } else {

                     

                    if (
                        $userId === $currentAdminId
                        && $status !== 'active'
                    ) {

                        $error = 'You cannot deactivate your own administrator account.';

                    } else {

                        if ($password !== '') {

                            if (strlen($password) < 6) {

                                $error = 'New password must contain at least 6 characters.';

                            } else {

                                $stmt = $pdo->prepare("
                                    UPDATE users
                                    SET
                                        name = :name,
                                        email = :email,
                                        password = :password,
                                        role = :role,
                                        status = :status,
                                        updated_at = NOW()
                                    WHERE id = :id
                                ");

                                $stmt->execute([
                                    'name' => $name,
                                    'email' => $email,
                                    'password' => password_hash(
                                        $password,
                                        PASSWORD_DEFAULT
                                    ),
                                    'role' => $role,
                                    'status' => $status,
                                    'id' => $userId
                                ]);

                                $success = 'User updated successfully.';
                            }

                        } else {

                            $stmt = $pdo->prepare("
                                UPDATE users
                                SET
                                    name = :name,
                                    email = :email,
                                    role = :role,
                                    status = :status,
                                    updated_at = NOW()
                                WHERE id = :id
                            ");

                            $stmt->execute([
                                'name' => $name,
                                'email' => $email,
                                'role' => $role,
                                'status' => $status,
                                'id' => $userId
                            ]);

                            $success = 'User updated successfully.';
                        }
                    }
                }
            }
        }


       

        elseif ($action === 'toggle_status') {

            $userId = filter_var(
                $_POST['user_id'] ?? '',
                FILTER_VALIDATE_INT
            );

            if (!$userId) {

                $error = 'Invalid user selected.';

            } elseif ($userId === $currentAdminId) {

                $error = 'You cannot deactivate your own administrator account.';

            } else {

                $stmt = $pdo->prepare("
                    SELECT status
                    FROM users
                    WHERE id = :id
                    LIMIT 1
                ");

                $stmt->execute([
                    'id' => $userId
                ]);

                $user = $stmt->fetch();

                if (!$user) {

                    $error = 'User not found.';

                } else {

                    $newStatus =
                        $user['status'] === 'active'
                            ? 'inactive'
                            : 'active';

                    $update = $pdo->prepare("
                        UPDATE users
                        SET
                            status = :status,
                            updated_at = NOW()
                        WHERE id = :id
                    ");

                    $update->execute([
                        'status' => $newStatus,
                        'id' => $userId
                    ]);

                    $success =
                        $newStatus === 'active'
                            ? 'User activated successfully.'
                            : 'User deactivated successfully.';
                }
            }
        }
    }
}

$editUser = null;

$editId = filter_input(
    INPUT_GET,
    'edit',
    FILTER_VALIDATE_INT
);

if ($editId) {

    $stmt = $pdo->prepare("
        SELECT
            id,
            name,
            email,
            role,
            status
        FROM users
        WHERE id = :id
        LIMIT 1
    ");

    $stmt->execute([
        'id' => $editId
    ]);

    $editUser = $stmt->fetch();

    if (!$editUser) {
        $error = 'The selected user was not found.';
    }
}



$search = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? '';
$roleFilter = $_GET['role'] ?? '';


$sql = "
    SELECT
        id,
        name,
        email,
        role,
        status,
        created_at,
        updated_at
    FROM users
    WHERE 1 = 1
";

$params = [];


if ($search !== '') {

    $sql .= "
        AND (
            name LIKE :search
            OR email LIKE :search
        )
    ";

    $params['search'] = '%' . $search . '%';
}


if (in_array($statusFilter, ['active', 'inactive'], true)) {

    $sql .= "
        AND status = :status
    ";

    $params['status'] = $statusFilter;
}


if (in_array($roleFilter, ['admin', 'user'], true)) {

    $sql .= "
        AND role = :role
    ";

    $params['role'] = $roleFilter;
}


$sql .= "
    ORDER BY id DESC
";


$stmt = $pdo->prepare($sql);

$stmt->execute($params);

$users = $stmt->fetchAll();




$totalUsers = (int) $pdo->query("
    SELECT COUNT(*)
    FROM users
")->fetchColumn();

$activeUsers = (int) $pdo->query("
    SELECT COUNT(*)
    FROM users
    WHERE status = 'active'
")->fetchColumn();

$inactiveUsers = (int) $pdo->query("
    SELECT COUNT(*)
    FROM users
    WHERE status = 'inactive'
")->fetchColumn();

$adminUsers = (int) $pdo->query("
    SELECT COUNT(*)
    FROM users
    WHERE role = 'admin'
")->fetchColumn();


require_once __DIR__ . '/../includes/header.php';

?>


<style>

.aw-users-top {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 15px;
    margin-bottom: 22px;
}

.aw-user-stat {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 11px;
    padding: 17px;
}

.aw-user-stat-label {
    color: #6b7280;
    font-size: 12px;
    margin-bottom: 7px;
}

.aw-user-stat-value {
    font-size: 26px;
    font-weight: 700;
    color: #111827;
}


.aw-user-layout {
    display: grid;
    grid-template-columns: 340px minmax(0, 1fr);
    gap: 20px;
    align-items: start;
}


.aw-user-card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,.05);
}


.aw-user-card h2 {
    margin: 0 0 18px;
    font-size: 19px;
}


.aw-form-group {
    margin-bottom: 14px;
}


.aw-form-group label {
    display: block;
    margin-bottom: 6px;
    font-size: 13px;
    font-weight: 600;
    color: #374151;
}


.aw-form-group input,
.aw-form-group select {
    width: 100%;
    box-sizing: border-box;
    padding: 10px 11px;
    border: 1px solid #d1d5db;
    border-radius: 7px;
    background: #fff;
    font-size: 14px;
}


.aw-form-group input:focus,
.aw-form-group select:focus {
    outline: none;
    border-color: #2563eb;
}


.aw-help {
    margin-top: 5px;
    color: #6b7280;
    font-size: 11px;
}


.aw-btn {
    display: inline-block;
    border: 0;
    border-radius: 7px;
    padding: 9px 13px;
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
}


.aw-btn-primary {
    background: #2563eb;
    color: #fff;
}


.aw-btn-secondary {
    background: #e5e7eb;
    color: #374151;
}


.aw-btn-danger {
    background: #fee2e2;
    color: #991b1b;
}


.aw-btn-success {
    background: #dcfce7;
    color: #166534;
}


.aw-alert {
    padding: 12px 14px;
    border-radius: 8px;
    margin-bottom: 18px;
    font-size: 13px;
}


.aw-alert-error {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fecaca;
}


.aw-alert-success {
    background: #dcfce7;
    color: #166534;
    border: 1px solid #bbf7d0;
}


.aw-filter-row {
    display: grid;
    grid-template-columns: minmax(180px, 1fr) 150px 150px auto;
    gap: 9px;
    margin-bottom: 17px;
}


.aw-filter-row input,
.aw-filter-row select {
    padding: 10px;
    border: 1px solid #d1d5db;
    border-radius: 7px;
    background: #fff;
}


.aw-users-table {
    width: 100%;
    border-collapse: collapse;
}


.aw-users-table th,
.aw-users-table td {
    padding: 11px 9px;
    border-bottom: 1px solid #eef0f3;
    text-align: left;
    vertical-align: top;
    font-size: 13px;
}


.aw-users-table th {
    background: #f8fafc;
    color: #374151;
}


.aw-user-name {
    font-weight: 700;
    color: #111827;
}


.aw-muted {
    color: #6b7280;
    font-size: 12px;
}


.aw-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 700;
    text-transform: capitalize;
}


.aw-badge-active {
    background: #dcfce7;
    color: #166534;
}


.aw-badge-inactive {
    background: #e5e7eb;
    color: #374151;
}


.aw-badge-admin {
    background: #dbeafe;
    color: #1d4ed8;
}


.aw-badge-user {
    background: #f3f4f6;
    color: #374151;
}


.aw-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}


.aw-inline-form {
    display: inline;
}


.aw-current {
    font-size: 10px;
    color: #2563eb;
    margin-left: 4px;
}


@media (max-width: 1100px) {

    .aw-user-layout {
        grid-template-columns: 1fr;
    }

}


@media (max-width: 800px) {

    .aw-users-top {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .aw-filter-row {
        grid-template-columns: 1fr;
    }

}


@media (max-width: 550px) {

    .aw-users-top {
        grid-template-columns: 1fr;
    }

}

</style>


<div class="aw-page-heading">

    <div>

        <h1>
            User Management
        </h1>

        <p class="aw-muted">
            Create, edit and manage AlertWatch user accounts.
        </p>

    </div>

</div>


<?php if ($error !== ''): ?>

    <div class="aw-alert aw-alert-error">
        <?= e($error) ?>
    </div>

<?php endif; ?>


<?php if ($success !== ''): ?>

    <div class="aw-alert aw-alert-success">
        <?= e($success) ?>
    </div>

<?php endif; ?>


<!--
|--------------------------------------------------------------------------
| USER STATISTICS
|--------------------------------------------------------------------------
-->

<div class="aw-users-top">

    <div class="aw-user-stat">

        <div class="aw-user-stat-label">
            Total Users
        </div>

        <div class="aw-user-stat-value">
            <?= $totalUsers ?>
        </div>

    </div>


    <div class="aw-user-stat">

        <div class="aw-user-stat-label">
            Active Users
        </div>

        <div class="aw-user-stat-value">
            <?= $activeUsers ?>
        </div>

    </div>


    <div class="aw-user-stat">

        <div class="aw-user-stat-label">
            Inactive Users
        </div>

        <div class="aw-user-stat-value">
            <?= $inactiveUsers ?>
        </div>

    </div>


    <div class="aw-user-stat">

        <div class="aw-user-stat-label">
            Administrators
        </div>

        <div class="aw-user-stat-value">
            <?= $adminUsers ?>
        </div>

    </div>

</div>


<div class="aw-user-layout">


    <!--
    |--------------------------------------------------------------------------
    | ADD / EDIT FORM
    |--------------------------------------------------------------------------
    -->

    <section class="aw-user-card">

        <h2>

            <?= $editUser
                ? 'Edit User'
                : 'Add User'
            ?>

        </h2>


        <form method="post">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= e(csrfToken()) ?>"
            >


            <input
                type="hidden"
                name="action"
                value="<?= $editUser ? 'edit' : 'add' ?>"
            >


            <?php if ($editUser): ?>

                <input
                    type="hidden"
                    name="user_id"
                    value="<?= (int)$editUser['id'] ?>"
                >

            <?php endif; ?>


            <div class="aw-form-group">

                <label>
                    Full Name
                </label>

                <input
                    type="text"
                    name="name"
                    required
                    maxlength="100"
                    value="<?= e(
                        $editUser['name'] ?? ''
                    ) ?>"
                >

            </div>


            <div class="aw-form-group">

                <label>
                    Email Address
                </label>

                <input
                    type="email"
                    name="email"
                    required
                    maxlength="150"
                    value="<?= e(
                        $editUser['email'] ?? ''
                    ) ?>"
                >

            </div>


            <div class="aw-form-group">

                <label>

                    Password

                    <?php if ($editUser): ?>

                        <span class="aw-help">
                            Leave blank to keep the current password.
                        </span>

                    <?php endif; ?>

                </label>

                <input
                    type="password"
                    name="password"
                    minlength="6"
                    <?= $editUser ? '' : 'required' ?>
                >

                <?php if (!$editUser): ?>

                    <div class="aw-help">
                        Minimum 6 characters.
                    </div>

                <?php endif; ?>

            </div>


            <div class="aw-form-group">

                <label>
                    Role
                </label>

                <select name="role">

                    <option
                        value="user"
                        <?= (($editUser['role'] ?? 'user') === 'user')
                            ? 'selected'
                            : ''
                        ?>
                    >
                        User
                    </option>

                    <option
                        value="admin"
                        <?= (($editUser['role'] ?? '') === 'admin')
                            ? 'selected'
                            : ''
                        ?>
                    >
                        Administrator
                    </option>

                </select>

            </div>


            <div class="aw-form-group">

                <label>
                    Status
                </label>

                <select name="status">

                    <option
                        value="active"
                        <?= (($editUser['status'] ?? 'active') === 'active')
                            ? 'selected'
                            : ''
                        ?>
                    >
                        Active
                    </option>

                    <option
                        value="inactive"
                        <?= (($editUser['status'] ?? '') === 'inactive')
                            ? 'selected'
                            : ''
                        ?>
                    >
                        Inactive
                    </option>

                </select>

            </div>


            <button
                type="submit"
                class="aw-btn aw-btn-primary"
            >

                <?= $editUser
                    ? 'Update User'
                    : 'Create User'
                ?>

            </button>


            <?php if ($editUser): ?>

                <a
                    href="<?= e(BASE_URL) ?>/admin/users.php"
                    class="aw-btn aw-btn-secondary"
                >
                    Cancel
                </a>

            <?php endif; ?>


        </form>

    </section>





    <section class="aw-user-card">

        <h2>
            Users
        </h2>


        <form
            method="get"
            class="aw-filter-row"
        >

            <input
                type="text"
                name="search"
                placeholder="Search name or email..."
                value="<?= e($search) ?>"
            >


            <select name="role">

                <option value="">
                    All Roles
                </option>

                <option
                    value="admin"
                    <?= $roleFilter === 'admin'
                        ? 'selected'
                        : ''
                    ?>
                >
                    Administrators
                </option>

                <option
                    value="user"
                    <?= $roleFilter === 'user'
                        ? 'selected'
                        : ''
                    ?>
                >
                    Users
                </option>

            </select>


            <select name="status">

                <option value="">
                    All Status
                </option>

                <option
                    value="active"
                    <?= $statusFilter === 'active'
                        ? 'selected'
                        : ''
                    ?>
                >
                    Active
                </option>

                <option
                    value="inactive"
                    <?= $statusFilter === 'inactive'
                        ? 'selected'
                        : ''
                    ?>
                >
                    Inactive
                </option>

            </select>


            <button
                type="submit"
                class="aw-btn aw-btn-primary"
            >
                Search
            </button>

        </form>


        <?php if (!$users): ?>

            <p class="aw-muted">
                No users matched the selected filters.
            </p>

        <?php else: ?>

            <div style="overflow-x:auto;">

                <table class="aw-users-table">

                    <thead>

                        <tr>

                            <th>
                                User
                            </th>

                            <th>
                                Role
                            </th>

                            <th>
                                Status
                            </th>

                            <th>
                                Created
                            </th>

                            <th>
                                Actions
                            </th>

                        </tr>

                    </thead>


                    <tbody>

                    <?php foreach ($users as $user): ?>

                        <tr>

                            <td>

                                <div class="aw-user-name">

                                    <?= e(
                                        $user['name']
                                    ) ?>

                                    <?php if (
                                        (int)$user['id']
                                        === $currentAdminId
                                    ): ?>

                                        <span class="aw-current">
                                            You
                                        </span>

                                    <?php endif; ?>

                                </div>

                                <div class="aw-muted">

                                    <?= e(
                                        $user['email']
                                    ) ?>

                                </div>

                            </td>


                            <td>

                                <span
                                    class="aw-badge
                                    <?= $user['role'] === 'admin'
                                        ? 'aw-badge-admin'
                                        : 'aw-badge-user'
                                    ?>"
                                >

                                    <?= e(
                                        $user['role']
                                    ) ?>

                                </span>

                            </td>


                            <td>

                                <span
                                    class="aw-badge
                                    <?= $user['status'] === 'active'
                                        ? 'aw-badge-active'
                                        : 'aw-badge-inactive'
                                    ?>"
                                >

                                    <?= e(
                                        $user['status']
                                    ) ?>

                                </span>

                            </td>


                            <td>

                                <?= e(
                                    formatDateTime(
                                        $user['created_at']
                                    )
                                ) ?>

                            </td>


                            <td>

                                <div class="aw-actions">


                                    <a
                                        href="<?= e(
                                            BASE_URL
                                        ) ?>/admin/users.php?edit=<?= (int)$user['id'] ?>"
                                        class="aw-btn aw-btn-secondary"
                                    >
                                        Edit
                                    </a>


                                    <?php if (
                                        (int)$user['id']
                                        !== $currentAdminId
                                    ): ?>

                                        <form
                                            method="post"
                                            class="aw-inline-form"
                                        >

                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?= e(
                                                    csrfToken()
                                                ) ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="action"
                                                value="toggle_status"
                                            >

                                            <input
                                                type="hidden"
                                                name="user_id"
                                                value="<?= (int)$user['id'] ?>"
                                            >


                                            <button
                                                type="submit"
                                                class="aw-btn
                                                <?= $user['status'] === 'active'
                                                    ? 'aw-btn-danger'
                                                    : 'aw-btn-success'
                                                ?>"
                                            >

                                                <?= $user['status'] === 'active'
                                                    ? 'Deactivate'
                                                    : 'Activate'
                                                ?>

                                            </button>

                                        </form>

                                    <?php endif; ?>


                                </div>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>

    </section>


</div>


<?php

require_once __DIR__ . '/../includes/footer.php';

?>