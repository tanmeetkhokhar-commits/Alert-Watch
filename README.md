# AlertWatch
AlertWatch is a web-based public safety information management system developed to
collect, process, store and present emergency and public safety alerts from publicly
available external sources.

The system provides a centralised platform where users can view alerts, search and
filter information, manage alert subscriptions, and access structured alert information
through API endpoints.

## Project Overview
Emergency and public safety information is often distributed across multiple
government, emergency service and public information websites.

AlertWatch addresses this problem by collecting information from configured RSS/API
sources, processing and normalising the incoming data, storing it in a MySQL database,
and presenting the information through a web-based dashboard.

The system was developed as an academic software project and is currently considered
an implemented prototype/system rather than a production emergency-information
service.

## Main Objectives
The main objectives of AlertWatch are to:
- Collect alerts from configured external sources.
- Parse and normalise incoming RSS/Atom information.
- Prevent duplicate alert records.
- Store alert information in MySQL.
- Provide configurable external sources.
- Display alerts through a web dashboard.
- Allow users to search and filter alerts.
- Allow users to manage alert subscriptions.
- Provide JSON-based API access.
- Provide authentication and role-based administrative access.

## Key Features

### 1. Authentication
The system provides authentication and session-based access control.

Users must authenticate before accessing protected application functionality.

Administrative functionality is restricted according to the user's role.

### 2. Dashboard

The dashboard provides an overview of the information stored within AlertWatch. It provides access to the main application modules, including:

- Alerts
- Sources
- RSS Scheduler
- Subscriptions
- Administration

### 3. Alerts Module

The Alerts module allows users to:

- View alerts
- View individual alert details
- Search alerts
- Filter alerts
- Filter by severity
- Filter by category
- Filter by location
- Filter by source

### 4. Sources Module

The Sources module allows authorised users to manage external information sources.

Source information includes:

- Source name
- Source type
- Source URL
- Category
- Active/inactive status
- Last fetch information

### 5. RSS Processing

The RSS subsystem performs the following operations:

1. Fetch external RSS/Atom data.
2. Parse incoming records.
3. Extract common alert information.
4. Normalise the information.
5. Validate the data.
6. Check for duplicate records.
7. Store new alerts in MySQL.
8. Report processing results.


### 6. Duplicate Detection

AlertWatch uses the source identifier and external alert identifier to identify
previously processed records.

This prevents the same external alert from being inserted into the database multiple
times.

During testing, a test RSS source returned:

- 32 records retrieved
- 21 new alerts
- 11 duplicate alerts
- 0 failed records


### 7. Subscription Management

Users can manage their alert preferences through the subscription module.

Subscriptions can be associated with:

- Alert category
- Location
- Severity
- Notification method
- Subscription status


### 8. API

AlertWatch provides JSON-based API endpoints.

The API layer provides structured access to:

- Alerts
- Sources
- Subscriptions

The API separates machine-readable application data from the browser interface.

Example response structure:

```json
{
    "success": true,
    "count": 1,
    "alerts": []
}
