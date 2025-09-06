# Backend API Requirements for Task Management

## Missing Endpoints Required for Frontend Functionality

### 1. Delete Project Task Endpoint

**Missing**: `DELETE /time-management/project-tasks/{id}`

**Purpose**: Delete a project task by ID

**Request**:

- **Method**: DELETE
- **URL**: `/time-management/project-tasks/{id}`
- **Path Parameters**:
  - `id` (integer, required): The ID of the project task to delete
- **Headers**:
  - `Authorization: Bearer {token}` (required)
  - `Content-Type: application/json`

**Response**:

- **Success (200)**:
  ```json
  {
    "message": "Project task deleted successfully"
  }
  ```
- **Not Found (404)**:
  ```json
  {
    "message": "Project task not found"
  }
  ```
- **Unauthorized (401)**:
  ```json
  {
    "message": "Unauthorized access"
  }
  ```
- **Forbidden (403)**:
  ```json
  {
    "message": "You don't have permission to delete this project task"
  }
  ```
- **Server Error (500)**:
  ```json
  {
    "message": "Internal server error"
  }
  ```

**Business Logic**:

- Only the employee who created the project task should be able to delete it
- Verify the task belongs to the authenticated employee
- Remove the task from the database
- Update any related project assignment statistics if needed
- Log the deletion activity for audit purposes

### 2. Time Logging for Assigned Tasks Endpoint

**Missing**: `POST /time-management/assigned-tasks/{id}/time-spent`

**Purpose**: Log time spent on an assigned task

**Request**:

- **Method**: POST
- **URL**: `/time-management/assigned-tasks/{id}/time-spent`
- **Path Parameters**:
  - `id` (integer, required): The ID of the assigned task
- **Headers**:
  - `Authorization: Bearer {token}` (required)
  - `Content-Type: application/json`
- **Body**:
  ```json
  {
    "hours": 2.5,
    "date": "2025-09-07",
    "description": "Worked on task implementation and testing"
  }
  ```

**Response**:

- **Success (201)**:
  ```json
  {
    "message": "Time logged successfully",
    "data": {
      "task_id": 123,
      "hours_logged": 2.5,
      "total_actual_hours": 8.5,
      "date": "2025-09-07"
    }
  }
  ```
- **Bad Request (400)**:
  ```json
  {
    "message": "Invalid data provided",
    "errors": {
      "hours": ["Hours must be greater than 0"],
      "date": ["Date format must be YYYY-MM-DD"]
    }
  }
  ```
- **Not Found (404)**:
  ```json
  {
    "message": "Assigned task not found"
  }
  ```
- **Unauthorized (401)**:
  ```json
  {
    "message": "Unauthorized access"
  }
  ```

**Business Logic**:

- Verify the assigned task exists and belongs to the authenticated employee
- Validate that hours is a positive number
- Validate that date is in correct format and not in the future
- Update the task's actual_hours field
- Create a time log entry for tracking purposes
- Update project statistics if the task is project-related

## Existing Endpoints - Verification Needed

Please verify these endpoints are working correctly as they are being used by the frontend:

### 1. Personal Task Pin Update

**Current**: `PUT /time-management/personal-tasks/{id}` with `{ "is_pinned": boolean }`

- **Issue**: Frontend calls `updatePersonalTaskPin` but endpoint might expect full task update
- **Verify**: Can we update just the `is_pinned` field without affecting other fields?

### 2. Project Task Pin Update

**Missing**: `PUT /time-management/project-tasks/{id}` with `{ "is_pinned": boolean }`

- **Current**: Only status update endpoint exists for project tasks
- **Needed**: Endpoint to update `is_pinned` and `is_important` fields individually

### 3. Assigned Task Pin Update

**Missing**: `PUT /time-management/assigned-tasks/{id}` with `{ "is_pinned": boolean, "is_important": boolean }`

- **Current**: Only status update endpoint exists
- **Needed**: Endpoint to update flags without changing status

## Request for Implementation

Please implement these endpoints with the exact specifications above. The frontend is already configured to use these endpoints, so matching the request/response format exactly is crucial for seamless integration.

**Priority Order**:

1. **HIGH**: Delete Project Task endpoint (blocking current functionality)
2. **MEDIUM**: Project Task Pin/Important update endpoints
3. **MEDIUM**: Assigned Task Time Logging endpoint
4. **LOW**: Assigned Task Pin/Important update endpoints

## Testing Requirements

For each endpoint, please test:

- Happy path with valid data
- Error handling with invalid data
- Authorization checks (employee can only access their own tasks)
- Proper HTTP status codes
- Response format matches specification exactly

## Database Considerations

Make sure the following database constraints are maintained:

- Soft deletes vs hard deletes (recommend soft deletes for audit trail)
- Foreign key constraints are properly handled during deletions
- Indexing on frequently queried fields (employee_id, status, is_pinned, is_important)
- Proper timestamp updates (updated_at field)
