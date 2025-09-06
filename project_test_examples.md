# Test Project Creation Without End Date

## Test Case 1: Project with End Date
```bash
curl -X POST http://127.0.0.1:8000/api/projects \
  -H "Content-Type: application/json" \
  -d '{
    "client_id": 1,
    "project_name": "Test Project With End Date",
    "department_id": 1,
    "start_date": "2025-09-02",
    "end_date": "2025-12-31",
    "project_manager_id": null,
    "notes": "Project with defined end date"
  }'
```

## Test Case 2: Project without End Date (null)
```bash
curl -X POST http://127.0.0.1:8000/api/projects \
  -H "Content-Type: application/json" \
  -d '{
    "client_id": 1,
    "project_name": "Test Project Without End Date - NULL",
    "department_id": 1,
    "start_date": "2025-09-02",
    "end_date": null,
    "project_manager_id": null,
    "notes": "Project without end date (null value)"
  }'
```

## Test Case 3: Project without End Date (omitted field)
```bash
curl -X POST http://127.0.0.1:8000/api/projects \
  -H "Content-Type: application/json" \
  -d '{
    "client_id": 1,
    "project_name": "Test Project Without End Date - Omitted",
    "department_id": 1,
    "start_date": "2025-09-02",
    "project_manager_id": null,
    "notes": "Project without end date (field omitted)"
  }'
```

## Test Case 4: Project without End Date (empty string)
```bash
curl -X POST http://127.0.0.1:8000/api/projects \
  -H "Content-Type: application/json" \
  -d '{
    "client_id": 1,
    "project_name": "Test Project Without End Date - Empty",
    "department_id": 1,
    "start_date": "2025-09-02",
    "end_date": "",
    "project_manager_id": null,
    "notes": "Project without end date (empty string)"
  }'
```
