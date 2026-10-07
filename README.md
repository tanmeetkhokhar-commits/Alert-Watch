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

 ## API

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
