# UEY Premium Mobility API Integration Documentation
### Frontend & QA Reference Guide (v1.0.0)

Welcome to the frontend integration guide for the **UEY Premium Mobility** backend platform. This document outlines the API specifications, request payloads, response templates, validation rules, and business logic for both the **Authentication** and **Driver Verification** modules.

---

## Global API Configuration & Conventions

### Base Gateway URL
All routes below are versioned and relative to the primary API gateway URL:
```
{{base_url}}/api/v1
```
*   **Local Development:** `http://uey.test/api/v1` or `http://localhost:8000/api/v1`
*   **Production Gateway:** `https://api.uey.mobility/v1` (or equivalent production domain)

### Standard Headers
For almost all endpoints, you must include the following headers:
```http
Accept: application/json
Content-Type: application/json
```
*Exception:* Document upload endpoints require `Content-Type: multipart/form-data`.

### Authentication Mechanism
The platform uses **Laravel Sanctum** Bearer tokens. For protected routes, pass the received token in the authorization header:
```http
Authorization: Bearer {{auth_token}}
```
*If a request lacks this header or the token is expired, the server will return a `401 Unauthorized` response.*

### Standard Error Responses

#### 1. Unauthenticated (401)
Returned when a Bearer token is missing, invalid, or expired.
```json
{
  "message": "Unauthenticated."
}
```

#### 2. Forbidden (403)
Returned when the authenticated user does not have the required role capability (e.g., a Rider trying to access Admin endpoints).
```json
{
  "message": "This action is unauthorized."
}
```

#### 3. Validation Errors (422)
Returned when input fields fail to meet specified validation rules.
```json
{
  "success": false,
  "message": "The given data was invalid.",
  "errors": {
    "phone": [
      "The phone field is required."
    ]
  }
}
```

---

## Module 1: Authentication & User Profile

### 1. Send OTP Code
*   **API Name:** Send OTP
*   **Purpose:** Generates and sends a 6-digit OTP code to the rider or driver's phone number for login or registration.
*   **Endpoint URL:** `/otp/send`
*   **HTTP Method:** `POST`
*   **Authentication Required:** No
*   **Headers:**
    *   `Accept: application/json`
    *   `Content-Type: application/json`
*   **Request Payload:**
    ```json
    {
      "phone": "+447911123456",
      "type": "register"
    }
    ```
*   **Success Response (200 OK):**
    *   **In Local/Testing Environment (`APP_ENV=local`):**
        ```json
        {
          "success": true,
          "message": "OTP sent successfully.",
          "otp": "654321"
        }
        ```
    *   **In Production Environment (`APP_ENV=production`):**
        ```json
        {
          "success": true,
          "message": "OTP sent successfully."
        }
        ```
*   **Error Response (422 Unprocessable Content):**
    ```json
    {
      "success": false,
      "message": "Rate limit exceeded. Please wait before requesting another OTP."
    }
    ```
*   **Validation Rules:**
    *   `phone`: Required, String, Min 8 characters, Max 20 characters. Must include country code (e.g., `+1` or `+44`).
    *   `type`: Required, Enum. Allowed values: `register`, `login`, `password_reset`.
*   **Business Logic Explanation:**
    *   Generates a 6-digit verification code.
    *   The OTP is valid for **5 minutes**.
    *   Rate limiting is enforced at **5 requests per minute** per phone number.
    *   In the local/development environment, the OTP is returned in the JSON payload so the frontend developer or QA tester doesn't need a real SMS gateway integration. In production, this field is completely omitted from the payload.
*   **Database Tables Affected:** `otp_verifications`
*   **Frontend Flow:**
    1.  User enters phone number and selects role/flow.
    2.  Frontend validates the string length and requests OTP.
    3.  On success, redirect user to the OTP verification input screen and start a 5-minute countdown.
*   **Example Use Case:** John wants to sign up for a UEY Rider account. He enters his phone number and clicks "Send OTP". The frontend invokes `/otp/send` with `type: "register"`.

---

### 2. Verify OTP Code
*   **API Name:** Verify OTP
*   **Purpose:** Verifies that the 6-digit code entered by the user matches the valid OTP sent to their phone number.
*   **Endpoint URL:** `/otp/verify`
*   **HTTP Method:** `POST`
*   **Authentication Required:** No
*   **Headers:**
    *   `Accept: application/json`
    *   `Content-Type: application/json`
*   **Request Payload:**
    ```json
    {
      "phone": "+447911123456",
      "code": "654321",
      "type": "register"
    }
    ```
*   **Success Response (200 OK):**
    ```json
    {
      "success": true,
      "message": "OTP verified successfully."
    }
    ```
*   **Error Response (422 Unprocessable Content):**
    ```json
    {
      "success": false,
      "message": "Invalid or expired OTP code."
    }
    ```
*   **Validation Rules:**
    *   `phone`: Required, String, Min 8, Max 20.
    *   `code`: Required, String, Exactly 6 characters.
    *   `type`: Required, Enum (`register`, `login`, `password_reset`).
*   **Business Logic Explanation:**
    *   Checks the latest record in `otp_verifications` for the phone number and flow type.
    *   Validates that the code matches and that it hasn't expired (within 5 minutes of generation).
    *   Marks the OTP as verified in the database on successful verification.
*   **Database Tables Affected:** `otp_verifications`
*   **Frontend Flow:**
    1.  User receives SMS or copies local-env OTP.
    2.  User inputs 6 digits on the screen.
    3.  On typing the 6th digit, the frontend triggers `/otp/verify`.
    4.  If verified successfully, proceed to the Registration/Login step.
*   **Example Use Case:** John enters the code `654321` on the screen. The frontend verifies it via `/otp/verify` before loading the registration form.

---

### 3. Register Rider
*   **API Name:** Register Rider
*   **Purpose:** Registers a new Rider profile, creates an automatic wallet, logs them in, and returns a Sanctum access token.
*   **Endpoint URL:** `/register/rider`
*   **HTTP Method:** `POST`
*   **Authentication Required:** No
*   **Headers:**
    *   `Accept: application/json`
    *   `Content-Type: application/json`
*   **Request Payload:**
    ```json
    {
      "name": "John Rider",
      "email": "john.rider@example.com",
      "phone": "+447911123456",
      "password": "password123"
    }
    ```
*   **Success Response (201 Created):**
    ```json
    {
      "success": true,
      "message": "Rider registered successfully.",
      "token": "1|abcde12345...",
      "user": {
        "id": 1,
        "name": "John Rider",
        "email": "john.rider@example.com",
        "phone": "+447911123456",
        "role": "rider",
        "status": "active",
        "avatar_url": null,
        "notification_preferences": {
          "email": true,
          "sms": true,
          "push": true
        },
        "created_at": "2026-06-23T00:58:13+05:30",
        "updated_at": "2026-06-23T00:58:13+05:30"
      }
    }
    ```
*   **Error Response (422 Unprocessable Content):**
    ```json
    {
      "success": false,
      "message": "The phone has already been taken.",
      "errors": {
        "phone": [
          "The phone has already been taken."
        ]
      }
    }
    ```
*   **Validation Rules:**
    *   `name`: Required, String, Max 255.
    *   `email`: Optional, Email format, Max 255, must be unique in `users` table.
    *   `phone`: Required, String, Min 8, Max 20, must be unique in `users` table.
    *   `password`: Required, String, Min 8.
*   **Business Logic Explanation:**
    *   Creates a new user record with `role = rider` and `status = active`.
    *   Automatically creates a wallet record linked to the user for future fare transactions.
    *   Generates a Sanctum token with `role:rider` ability and returns it.
*   **Database Tables Affected:** `users`, `wallets`
*   **Frontend Flow:**
    1.  Rider fills out profile details after OTP verification.
    2.  Rider clicks "Sign Up".
    3.  Frontend invokes `/register/rider`.
    4.  Frontend stores the returned `token` securely in local storage/keychain and redirects the rider to the main map screen.
*   **Example Use Case:** John fills in his email and name and sets his password. He registers successfully and is immediately directed to UEY's home booking screen.

---

### 4. Register Driver
*   **API Name:** Register Driver
*   **Purpose:** Registers a new Driver profile, creates a vehicle entry and wallet, logs them in, and returns a Sanctum access token.
*   **Endpoint URL:** `/register/driver`
*   **HTTP Method:** `POST`
*   **Authentication Required:** No
*   **Headers:**
    *   `Accept: application/json`
    *   `Content-Type: application/json`
*   **Request Payload:**
    ```json
    {
      "name": "Bob Driver",
      "email": "bob.driver@example.com",
      "phone": "+447911999999",
      "password": "password123",
      "license_number": "DL-999888",
      "license_expiry": "2027-06-21",
      "vehicle_make": "Toyota",
      "vehicle_model": "Prius",
      "vehicle_year": 2022,
      "vehicle_color": "Silver",
      "vehicle_plate": "ABC-999",
      "vehicle_type_id": 1
    }
    ```
*   **Success Response (201 Created):**
    ```json
    {
      "success": true,
      "message": "Driver registered successfully. Account is pending documents approval.",
      "token": "1|abcde12345...",
      "user": {
        "id": 2,
        "name": "Bob Driver",
        "email": "bob.driver@example.com",
        "phone": "+447911999999",
        "role": "driver",
        "status": "pending_approval",
        "avatar_url": null,
        "notification_preferences": {
          "email": true,
          "sms": true,
          "push": true
        },
        "driver_profile": {
          "id": 1,
          "license_number": "DL-999888",
          "license_expiry": "2027-06-21",
          "is_online": false,
          "rating": 5.0,
          "experience_years": 0.0,
          "acceptance_rate": 100.0,
          "ontime_rate": 100.0,
          "total_online_hours": 0,
          "preferences": {
            "default_navigation": "google_maps",
            "auto_accept": false
          },
          "vehicles": [
            {
              "id": 1,
              "make": "Toyota",
              "model": "Prius",
              "year": 2022,
              "color": "Silver",
              "plate_number": "ABC-999",
              "status": "pending"
            }
          ]
        },
        "created_at": "2026-06-23T00:58:13+05:30",
        "updated_at": "2026-06-23T00:58:13+05:30"
      }
    }
    ```
*   **Error Response (422 Unprocessable Content):**
    ```json
    {
      "success": false,
      "message": "The license number has already been taken.",
      "errors": {
        "license_number": [
          "The license_number has already been taken."
        ]
      }
    }
    ```
*   **Validation Rules:**
    *   `name`, `phone`, `password`: Same as rider.
    *   `email`: Optional, Email format, must be unique in `users`.
    *   `license_number`: Required, String, Max 100, unique in `driver_profiles`.
    *   `license_expiry`: Required, Date format, must be a future date (`after:today`).
    *   `vehicle_make`, `vehicle_model`: Required, String, Max 50.
    *   `vehicle_year`: Required, Integer, Min 1900, Max (Current Year + 1).
    *   `vehicle_color`: Required, String, Max 30.
    *   `vehicle_plate`: Required, String, Max 20, unique in `vehicles`.
    *   `vehicle_type_id`: Required, Integer, must exist in `vehicle_types` table.
*   **Business Logic Explanation:**
    *   Creates a user with `role = driver` and `status = pending_approval`.
    *   Creates a `driver_profiles` record containing licensing and configuration preferences (defaults navigation to `google_maps`).
    *   Creates a `vehicles` record linked to the driver profile with `status = pending`.
    *   Creates an automatic driver wallet.
    *   Logs the user in and returns a Sanctum access token with `role:driver` ability.
*   **Database Tables Affected:** `users`, `driver_profiles`, `vehicles`, `wallets`
*   **Frontend Flow:**
    1.  Driver registers by supplying driver credentials, license info, and vehicle specs.
    2.  Frontend invokes `/register/driver`.
    3.  Frontend saves the `token` and redirects the driver to the document upload onboarding screen.
*   **Example Use Case:** Bob registers as a driver with UEY. He completes registration and is sent straight to upload his Driver's License and Vehicle Insurance.

---

### 5. Login User
*   **API Name:** Login
*   **Purpose:** Authenticates any user using their registered phone number and password, returning user profiles and tokens.
*   **Endpoint URL:** `/login`
*   **HTTP Method:** `POST`
*   **Authentication Required:** No
*   **Headers:**
    *   `Accept: application/json`
    *   `Content-Type: application/json`
*   **Request Payload:**
    ```json
    {
      "phone": "+447911123456",
      "password": "password123"
    }
    ```
*   **Success Response (200 OK):**
    ```json
    {
      "success": true,
      "message": "Logged in successfully.",
      "token": "1|abcde12345...",
      "user": {
        "id": 1,
        "name": "John Rider",
        "email": "john.rider@example.com",
        "phone": "+447911123456",
        "role": "rider",
        "status": "active",
        "avatar_url": null,
        "notification_preferences": {
          "email": true,
          "sms": true,
          "push": true
        },
        "created_at": "2026-06-23T00:58:13+05:30",
        "updated_at": "2026-06-23T00:58:13+05:30"
      }
    }
    ```
*   **Error Response (422 Unprocessable Content):**
    ```json
    {
      "success": false,
      "message": "Invalid phone number or password."
    }
    ```
*   **Validation Rules:**
    *   `phone`: Required, String, Min 8, Max 20.
    *   `password`: Required, String.
*   **Business Logic Explanation:**
    *   Finds user matching phone number.
    *   Verifies password hashes.
    *   If correct, generates and returns a Sanctum Bearer token with the appropriate role-based ability (e.g., `role:rider` or `role:driver` or `role:admin`).
*   **Database Tables Affected:** `users`, `personal_access_tokens`
*   **Frontend Flow:**
    1.  User enters phone and password.
    2.  Frontend invokes `/login`.
    3.  Frontend saves the `token` and checks the user's `role` and `status` to determine the navigation route (e.g. Riders go to Booking; Drivers go to Onboarding or Map depending on verification status).
*   **Example Use Case:** John logs into UEY with his credentials to book a premium ride.

---

### 6. Logout User
*   **API Name:** Logout
*   **Purpose:** Revokes the authenticated user's current access token.
*   **Endpoint URL:** `/logout`
*   **HTTP Method:** `POST`
*   **Authentication Required:** Yes
*   **Headers:**
    *   `Accept: application/json`
    *   `Authorization: Bearer {{auth_token}}`
*   **Request Payload:** None (Empty body)
*   **Success Response (200 OK):**
    ```json
    {
      "success": true,
      "message": "Logged out successfully."
    }
    ```
*   **Error Response (401 Unauthorized):**
    ```json
    {
      "message": "Unauthenticated."
    }
    ```
*   **Validation Rules:** None.
*   **Business Logic Explanation:**
    *   Deletes the user's current token from the `personal_access_tokens` table, immediately invalidating the session.
*   **Database Tables Affected:** `personal_access_tokens`
*   **Frontend Flow:**
    1.  User taps "Logout" in app settings.
    2.  Frontend makes a POST call to `/logout` with token.
    3.  On success, frontend clears stored local tokens and redirects to the OTP sign-in screen.
*   **Example Use Case:** John logs out of the app to switch devices.

---

### 7. Refresh Token
*   **API Name:** Refresh Token
*   **Purpose:** Rotates Sanctum tokens by revoking the current one and issuing a brand new token.
*   **Endpoint URL:** `/token/refresh`
*   **HTTP Method:** `POST`
*   **Authentication Required:** Yes
*   **Headers:**
    *   `Accept: application/json`
    *   `Authorization: Bearer {{auth_token}}`
*   **Request Payload:** None (Empty body)
*   **Success Response (200 OK):**
    ```json
    {
      "success": true,
      "token": "2|zYxWvUtSrQ..."
    }
    ```
*   **Error Response (401 Unauthorized):**
    ```json
    {
      "message": "Unauthenticated."
    }
    ```
*   **Validation Rules:** None.
*   **Business Logic Explanation:**
    *   Deletes the calling token from the `personal_access_tokens` table.
    *   Issues a new token with identical role permissions.
*   **Database Tables Affected:** `personal_access_tokens`
*   **Frontend Flow:**
    1.  Frontend intercepts a response or decides to renew session validity.
    2.  Sends request to `/token/refresh`.
    3.  Replaces the old token with the new `token` in local storage for subsequent calls.
*   **Example Use Case:** The app rotates tokens every week for security. The client app performs this silent refresh in the background.

---

### 8. Get User Profile
*   **API Name:** Get Profile
*   **Purpose:** Retrieves complete profile details for the authenticated user, including role-specific sub-profiles.
*   **Endpoint URL:** `/profile`
*   **HTTP Method:** `GET`
*   **Authentication Required:** Yes
*   **Headers:**
    *   `Accept: application/json`
    *   `Authorization: Bearer {{auth_token}}`
*   **Request Payload:** None
*   **Success Response (200 OK):**
    *   **If authenticated user is a Rider:**
        ```json
        {
          "success": true,
          "user": {
            "id": 1,
            "name": "John Rider",
            "email": "john.rider@example.com",
            "phone": "+447911123456",
            "role": "rider",
            "status": "active",
            "avatar_url": null,
            "notification_preferences": {
              "email": true,
              "sms": true,
              "push": true
            },
            "created_at": "2026-06-23T00:58:13+05:30",
            "updated_at": "2026-06-23T00:58:13+05:30"
          }
        }
        ```
    *   **If authenticated user is a Driver:**
        ```json
        {
          "success": true,
          "user": {
            "id": 2,
            "name": "Bob Driver",
            "email": "bob.driver@example.com",
            "phone": "+447911999999",
            "role": "driver",
            "status": "pending_approval",
            "avatar_url": null,
            "notification_preferences": {
              "email": true,
              "sms": true,
              "push": true
            },
            "driver_profile": {
              "id": 1,
              "license_number": "DL-999888",
              "license_expiry": "2027-06-21",
              "is_online": false,
              "rating": 5.0,
              "experience_years": 0.0,
              "acceptance_rate": 100.0,
              "ontime_rate": 100.0,
              "total_online_hours": 0,
              "preferences": {
                "default_navigation": "google_maps",
                "auto_accept": false
              },
              "vehicles": [
                {
                  "id": 1,
                  "make": "Toyota",
                  "model": "Prius",
                  "year": 2022,
                  "color": "Silver",
                  "plate_number": "ABC-999",
                  "status": "pending"
                }
              ]
            },
            "created_at": "2026-06-23T00:58:13+05:30",
            "updated_at": "2026-06-23T00:58:13+05:30"
          }
        }
        ```
*   **Error Response (401 Unauthorized):**
    ```json
    {
      "message": "Unauthenticated."
    }
    ```
*   **Validation Rules:** None.
*   **Business Logic Explanation:**
    *   Loads profile details based on Bearer token identification.
    *   If the user has a Driver role, eagerly loads `driverProfile` relations including vehicle detail lists to minimize frontend roundtrips.
*   **Database Tables Affected:** `users`, `driver_profiles`, `vehicles` (reads)
*   **Frontend Flow:**
    1.  On app launch/restore, the frontend checks if a token exists.
    2.  If yes, triggers `/profile` to fetch up-to-date settings and verify token validity.
    3.  Caches the user's role and status locally.
*   **Example Use Case:** Bob opens UEY, and the app fetches his driver status to show either the verification wizard or the online/offline switch.

---

### 9. Update Profile Settings
*   **API Name:** Update Profile
*   **Purpose:** Updates the user's personal details and notification preferences.
*   **Endpoint URL:** `/profile`
*   **HTTP Method:** `PUT`
*   **Authentication Required:** Yes
*   **Headers:**
    *   `Accept: application/json`
    *   `Content-Type: application/json`
    *   `Authorization: Bearer {{auth_token}}`
*   **Request Payload:**
    ```json
    {
      "name": "Jane Updated",
      "email": "jane.updated@example.com",
      "avatar_url": "https://example.com/avatar.png",
      "email_notifications": true,
      "sms_notifications": false,
      "push_notifications": true,
      "default_navigation": "google_maps",
      "auto_accept": true
    }
    ```
*   **Success Response (200 OK):**
    ```json
    {
      "success": true,
      "message": "Profile updated successfully.",
      "user": {
        "id": 1,
        "name": "Jane Updated",
        "email": "jane.updated@example.com",
        "phone": "+447911123456",
        "role": "rider",
        "status": "active",
        "avatar_url": "https://example.com/avatar.png",
        "notification_preferences": {
          "email": true,
          "sms": false,
          "push": true
        },
        "created_at": "2026-06-23T00:58:13+05:30",
        "updated_at": "2026-06-23T01:13:46+05:30"
      }
    }
    ```
*   **Error Response (422 Unprocessable Content):**
    ```json
    {
      "success": false,
      "message": "The given data was invalid.",
      "errors": {
        "default_navigation": [
          "The selected default navigation is invalid."
        ]
      }
    }
    ```
*   **Validation Rules:**
    *   `name`: Optional/Sometimes, String, Max 255.
    *   `email`: Optional/Sometimes, Email format, Max 255, must be unique in `users` (except current user).
    *   `avatar_url`: Optional/Sometimes, URL, Max 2048.
    *   `email_notifications`, `sms_notifications`, `push_notifications`: Optional, Boolean.
    *   `default_navigation`: Optional (Only evaluated for Drivers), Enum (`google_maps`, `waze`, `apple_maps`).
    *   `auto_accept`: Optional (Only evaluated for Drivers), Boolean.
*   **Business Logic Explanation:**
    *   Allows updating base profile information.
    *   If the user has a Driver role, it also updates specific preferences on the linked `driver_profiles` table (e.g., `default_navigation`, `auto_accept`).
*   **Database Tables Affected:** `users`, `driver_profiles`
*   **Frontend Flow:**
    1.  User edits details in the Settings or Edit Profile tab.
    2.  Taps "Save Settings".
    3.  Frontend invokes `PUT /profile` with only modified parameters.
    4.  Updates UI values with the returned user model.
*   **Example Use Case:** Jane wants to disable SMS alerts and change her name. She updates these in her Profile screen and saves.

---

## Module 2: Driver Verification & Onboarding

### 10. Upload Driver Document
*   **API Name:** Upload Document
*   **Purpose:** Uploads driver verification documents (license, registration, or insurance). Also supports re-uploading documents.
*   **Endpoint URL:** `/driver/onboarding/documents`
*   **HTTP Method:** `POST`
*   **Authentication Required:** Yes
*   **Headers:**
    *   `Accept: application/json`
    *   `Content-Type: multipart/form-data`
    *   `Authorization: Bearer {{auth_token}}`
*   **Request Payload (Form-Data):**
    *   `document_type`: `driving_license` | `vehicle_registration` | `insurance` | `police_clearance` (Required)
    *   `document`: File/Binary (Required, Max 5MB, format: jpg, jpeg, png, pdf)
    *   `expires_at`: `2028-12-31` (Optional, Date must be `after:today`)
*   **Success Response (201 Created):**
    ```json
    {
      "success": true,
      "message": "Document uploaded successfully.",
      "document": {
        "id": 1,
        "document_type": "driving_license",
        "document_path": "documents/driving_license_123.jpg",
        "document_url": "http://uey.test/storage/documents/driving_license_123.jpg",
        "status": "pending",
        "rejection_reason": null,
        "expires_at": "2028-12-31",
        "created_at": "2026-06-23T00:58:13+05:30",
        "updated_at": "2026-06-23T00:58:13+05:30"
      }
    }
    ```
*   **Error Response (422 Unprocessable Content):**
    ```json
    {
      "success": false,
      "message": "The document must be a file of type: jpg, jpeg, png, pdf."
    }
    ```
*   **Validation Rules:**
    *   `document_type`: Required, Enum (`driving_license`, `vehicle_registration`, `insurance`, `police_clearance`).
    *   `document`: Required, File, format must be jpg, jpeg, png, or pdf, size <= 5120 KB (5MB).
    *   `expires_at`: Optional, Date format, must be in the future.
*   **Business Logic Explanation:**
    *   Restricted to drivers only (requires `role:driver` token ability).
    *   Saves the uploaded file to storage under `documents/` path structure.
    *   If the driver has already uploaded a document of that type, the system replaces the old file, clears any existing `rejection_reason`, and resets the document's verification status back to `pending`.
*   **Database Tables Affected:** `driver_documents`
*   **Frontend Flow:**
    1.  Driver taps "Upload" on onboarding checklist.
    2.  Driver selects file/takes photo.
    3.  Frontend compiles payload into `FormData` and sends `POST` request.
    4.  Checkpoint refreshes checklist screen on successful 201 response.
*   **Example Use Case:** Bob takes a photo of his new vehicle insurance card and uploads it to complete UEY verification.

---

### 11. Get Onboarding Status
*   **API Name:** Get Onboarding Status
*   **Purpose:** Fetches the driver's overall onboarding status, requirement checklist status, and list of uploaded documents.
*   **Endpoint URL:** `/driver/onboarding/status`
*   **HTTP Method:** `GET`
*   **Authentication Required:** Yes (Driver Only)
*   **Headers:**
    *   `Accept: application/json`
    *   `Authorization: Bearer {{auth_token}}`
*   **Request Payload:** None
*   **Success Response (200 OK):**
    ```json
    {
      "success": true,
      "onboarding": {
        "driver_profile_id": 1,
        "overall_status": "pending_approval",
        "vehicle_status": "pending",
        "bank_account_completed": false,
        "can_go_online": false,
        "requirements": {
          "documents_approved": false,
          "vehicle_approved": false,
          "bank_account_linked": false
        },
        "documents": [
          {
            "id": 1,
            "document_type": "driving_license",
            "document_path": "documents/driving_license_123.jpg",
            "document_url": "http://uey.test/storage/documents/driving_license_123.jpg",
            "status": "pending",
            "rejection_reason": null,
            "expires_at": "2028-12-31"
          }
        ],
        "vehicle": {
          "id": 1,
          "make": "Toyota",
          "model": "Prius",
          "year": 2022,
          "color": "Silver",
          "plate_number": "ABC-999",
          "status": "pending"
        }
      }
    }
    ```
*   **Error Response (403 Forbidden):**
    ```json
    {
      "message": "This action is unauthorized."
    }
    ```
*   **Validation Rules:** None.
*   **Business Logic Explanation:**
    *   Retrieves driver profile and relationships (vehicle, documents, bankAccount).
    *   Returns boolean checkmarks: `documents_approved` is true only when all three essential documents (`driving_license`, `vehicle_registration`, `insurance`) are `approved`.
    *   `can_go_online` represents if the driver is fully active and ready to accept rides (user is active, vehicle is approved, documents are approved).
*   **Database Tables Affected:** `users`, `driver_profiles`, `vehicles`, `driver_documents`, `driver_bank_accounts` (reads)
*   **Frontend Flow:**
    1.  App navigates to Driver Onboarding Checklist Screen.
    2.  Queries `/driver/onboarding/status`.
    3.  Frontend renders tick/cross icons based on `requirements` booleans and shows details/reasons for rejected documents.
*   **Example Use Case:** Bob navigates to UEY dashboard. The checklist shows "Vehicle Approved" (Green tick) and "Bank Account Linked" (Green tick) but "Documents Approved" (Red cross) because his license photo was rejected.

---

### 12. Save Bank Account Details
*   **API Name:** Link/Update Bank Account
*   **Purpose:** Links or updates the bank details for driver payouts.
*   **Endpoint URL:** `/driver/bank-account`
*   **HTTP Method:** `POST`
*   **Authentication Required:** Yes (Driver Only)
*   **Headers:**
    *   `Accept: application/json`
    *   `Content-Type: application/json`
    *   `Authorization: Bearer {{auth_token}}`
*   **Request Payload:**
    ```json
    {
      "bank_name": "Chase Bank",
      "account_holder_name": "Bob Driver",
      "account_number": "1234567890",
      "routing_number": "987654321",
      "swift_code": "CHASUS33"
    }
    ```
*   **Success Response (200 OK):**
    ```json
    {
      "success": true,
      "message": "Bank account saved successfully.",
      "bank_account": {
        "id": 1,
        "bank_name": "Chase Bank",
        "account_holder_name": "Bob Driver",
        "account_number_masked": "******7890",
        "routing_number": "987654321",
        "swift_code": "CHASUS33",
        "created_at": "2026-06-23T00:58:13+05:30",
        "updated_at": "2026-06-23T00:58:13+05:30"
      }
    }
    ```
*   **Error Response (422 Unprocessable Content):**
    ```json
    {
      "success": false,
      "message": "The bank name field is required."
    }
    ```
*   **Validation Rules:**
    *   `bank_name`: Required, String, Max 100.
    *   `account_holder_name`: Required, String, Max 255.
    *   `account_number`: Required, String, Max 50.
    *   `routing_number`, `swift_code`: Optional, String, Max 50.
*   **Business Logic Explanation:**
    *   Driver-only endpoint.
    *   Bank account details are stored securely. The `account_number` is **encrypted** automatically in the database.
    *   The API response masks the bank account number (showing only the last 4 digits) to protect sensitive data on the client side.
*   **Database Tables Affected:** `driver_bank_accounts`
*   **Frontend Flow:**
    1.  Driver navigates to "Bank Details" form.
    2.  Fills out bank info and clicks "Save Bank Details".
    3.  Frontend invokes `/driver/bank-account` POST.
    4.  Redirects back to checklist.
*   **Example Use Case:** Bob wants to set up payouts. He enters his account details and saves.

---

### 13. Get Bank Account Details
*   **API Name:** Get Bank Account
*   **Purpose:** Retrieves the linked bank account details.
*   **Endpoint URL:** `/driver/bank-account`
*   **HTTP Method:** `GET`
*   **Authentication Required:** Yes (Driver Only)
*   **Headers:**
    *   `Accept: application/json`
    *   `Authorization: Bearer {{auth_token}}`
*   **Request Payload:** None
*   **Success Response (200 OK):**
    ```json
    {
      "success": true,
      "bank_account": {
        "id": 1,
        "bank_name": "Chase Bank",
        "account_holder_name": "Bob Driver",
        "account_number_masked": "******7890",
        "routing_number": "987654321",
        "swift_code": "CHASUS33",
        "created_at": "2026-06-23T00:58:13+05:30",
        "updated_at": "2026-06-23T00:58:13+05:30"
      }
    }
    ```
*   **Error Response (404 Not Found):**
    ```json
    {
      "success": false,
      "message": "Bank account details not found."
    }
    ```
*   **Validation Rules:** None.
*   **Business Logic Explanation:**
    *   Fetches the bank details of the driver.
    *   The account number is decrypted on-the-fly inside the service layer but is returned **masked** (e.g. `******7890`) for security.
*   **Database Tables Affected:** `driver_bank_accounts` (read)
*   **Frontend Flow:**
    1.  Driver opens "Bank Details" view.
    2.  Queries `/driver/bank-account`.
    3.  If 404 is returned, show empty form; if 200 is returned, render pre-filled masked parameters.
*   **Example Use Case:** Bob clicks "View payout bank account" to review his linked settings.

---

### 14. Admin: View Pending Documents
*   **API Name:** Get Pending Documents
*   **Purpose:** Retrieves a list of all uploaded driver documents currently pending verification.
*   **Endpoint URL:** `/admin/documents/pending`
*   **HTTP Method:** `GET`
*   **Authentication Required:** Yes (Admin Only)
*   **Headers:**
    *   `Accept: application/json`
    *   `Authorization: Bearer {{auth_token}}`
*   **Request Payload:** None
*   **Success Response (200 OK):**
    ```json
    {
      "success": true,
      "documents": [
        {
          "id": 1,
          "document_type": "driving_license",
          "document_path": "documents/driving_license_123.jpg",
          "document_url": "http://uey.test/storage/documents/driving_license_123.jpg",
          "status": "pending",
          "rejection_reason": null,
          "expires_at": "2028-12-31",
          "created_at": "2026-06-23T00:58:13+05:30",
          "updated_at": "2026-06-23T00:58:13+05:30"
        }
      ]
    }
    ```
*   **Error Response (403 Forbidden):**
    ```json
    {
      "message": "This action is unauthorized."
    }
    ```
*   **Validation Rules:** None.
*   **Business Logic Explanation:**
    *   Restricted to admins only (requires `role:admin` Sanctum ability).
    *   Finds all rows in `driver_documents` where `status = pending`.
    *   Eagerly loads the driver user details for display.
*   **Database Tables Affected:** `driver_documents` (read)
*   **Frontend Flow:**
    1.  Admin opens UEY Admin Verification Portal dashboard.
    2.  Queries `/admin/documents/pending`.
    3.  Renders listing cards.
*   **Example Use Case:** Admin log in to check pending tasks and views a list of documents submitted by onboarding drivers.

---

### 15. Admin: Verify Document
*   **API Name:** Verify Driver Document
*   **Purpose:** Admin approves or rejects an onboarding driver document. Rejections require a reason.
*   **Endpoint URL:** `/admin/documents/{document}/verify`
*   **HTTP Method:** `POST`
*   **Authentication Required:** Yes (Admin Only)
*   **Headers:**
    *   `Accept: application/json`
    *   `Content-Type: application/json`
    *   `Authorization: Bearer {{auth_token}}`
*   **Parameters:**
    *   `document`: (Path parameter, Integer) The ID of the `driver_documents` row to verify.
*   **Request Payload (JSON):**
    *   **Approval:**
        ```json
        {
          "status": "approved"
        }
        ```
    *   **Rejection:**
        ```json
        {
          "status": "rejected",
          "rejection_reason": "The driver photo on the license is blurry."
        }
        ```
*   **Success Response (200 OK):**
    ```json
    {
      "success": true,
      "message": "Document has been approved successfully.",
      "document": {
        "id": 1,
        "document_type": "driving_license",
        "document_path": "documents/driving_license_123.jpg",
        "document_url": "http://uey.test/storage/documents/driving_license_123.jpg",
        "status": "approved",
        "rejection_reason": null,
        "expires_at": "2028-12-31",
        "created_at": "2026-06-23T00:58:13+05:30",
        "updated_at": "2026-06-23T01:13:46+05:30"
      }
    }
    ```
*   **Error Response (422 Unprocessable Content):**
    ```json
    {
      "success": false,
      "message": "The rejection reason field is required when status is rejected."
    }
    ```
*   **Validation Rules:**
    *   `status`: Required, Enum (`approved`, `rejected`).
    *   `rejection_reason`: Required if `status` is `rejected`. String, Max 1000.
*   **Business Logic Explanation:**
    *   Admin-only endpoint.
    *   Updates the document status.
    *   **Auto-activation trigger:** When a document transitions to `approved`, the service checks if the driver now has all **three** core documents approved (`driving_license`, `vehicle_registration`, `insurance`) **AND** a registered vehicle.
    *   If both conditions are met, the driver's vehicle status is automatically changed to `approved` and the driver's overall user status is changed to `active` in the database.
*   **Database Tables Affected:** `driver_documents`, `vehicles`, `users`
*   **Frontend Flow:**
    1.  Admin views document details in review module.
    2.  Clicks "Approve" or "Reject". If "Reject", triggers modal prompt to enter a rejection reason.
    3.  Frontend submits POST with status.
    4.  Refreshing admin listing on success.
*   **Example Use Case:** Bob's driving license is approved. Because his vehicle registration and insurance were already approved, Bob is automatically marked active and can immediately slide to online in UEY.
