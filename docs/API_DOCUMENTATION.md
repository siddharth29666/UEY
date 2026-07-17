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

### 9a. Request Password Reset OTP
*   **API Name:** Request Password Reset OTP
*   **Purpose:** Requests a 6-digit password reset OTP code. Sends the code to the user's email.
*   **Endpoint URL:** `/auth/forgot-password`
*   **HTTP Method:** `POST`
*   **Authentication Required:** No
*   **Headers:**
    *   `Accept: application/json`
    *   `Content-Type: application/json`
*   **Request Payload:**
    ```json
    {
      "email": "user@example.com"
    }
    ```
*   **Success Response (200 OK):**
    ```json
    {
      "success": true,
      "message": "Password reset OTP sent successfully."
    }
    ```
*   **Error Response (422 Unprocessable Content):**
    ```json
    {
      "success": false,
      "message": "The given data was invalid.",
      "errors": {
        "email": [
          "The selected email is invalid."
        ]
      }
    }
    ```
*   **Validation Rules:**
    *   `email`: Required, Email format, must exist in `users.email`.
*   **Business Logic Explanation:**
    *   Validates that the email belongs to a registered user.
    *   Generates a 6-digit OTP code, hashes it, and stores/updates it in `password_reset_tokens` table.
    *   Dispatches an email notification containing the OTP.
*   **Database Tables Affected:** `password_reset_tokens`
*   **Example Use Case:** A user forgot their password and inputs their email to receive a recovery code.

---

### 9b. Verify OTP & Reset Password
*   **API Name:** Verify OTP & Reset Password
*   **Purpose:** Verifies the 6-digit recovery OTP code and updates the user's password.
*   **Endpoint URL:** `/auth/reset-password`
*   **HTTP Method:** `POST`
*   **Authentication Required:** No
*   **Headers:**
    *   `Accept: application/json`
    *   `Content-Type: application/json`
*   **Request Payload:**
    ```json
    {
      "email": "user@example.com",
      "otp": "123456",
      "password": "NewPassword123!",
      "password_confirmation": "NewPassword123!"
    }
    ```
*   **Success Response (200 OK):**
    ```json
    {
      "success": true,
      "message": "Password reset successfully."
    }
    ```
*   **Error Response (422 Unprocessable Content):**
    ```json
    {
      "success": false,
      "message": "The given data was invalid.",
      "errors": {
        "otp": [
          "The provided OTP is invalid."
        ]
      }
    }
    ```
*   **Validation Rules:**
    *   `email`: Required, Email format, must exist in `users`.
    *   `otp`: Required, String, exactly 6 characters.
    *   `password`: Required, String, minimum 8 characters, must match `password_confirmation`.
*   **Business Logic Explanation:**
    *   Validates the OTP code against the record in `password_reset_tokens`.
    *   Verifies that the OTP is not older than 10 minutes (expiry check).
    *   Updates the user's password securely using Hash::make().
    *   Invalidates the OTP and deletes the reset token record.
    *   Revokes all active Sanctum tokens for the user to ensure all active sessions are logged out.
*   **Database Tables Affected:** `users`, `password_reset_tokens`, `personal_access_tokens`
*   **Example Use Case:** A user receives the 6-digit code via email, enters it with their new password, and resets it.

---

### 9c. Delete User Account
*   **API Name:** Delete User Account
*   **Purpose:** Permanently deletes (soft-deletes) the authenticated user's account and cleans up sensitive related profile data.
*   **Endpoint URL:** `/profile/delete-account`
*   **HTTP Method:** `DELETE`
*   **Authentication Required:** Yes
*   **Headers:**
    *   `Accept: application/json`
    *   `Authorization: Bearer {{auth_token}}`
*   **Request Payload:**
    ```json
    {
      "password": "CurrentPassword123!"
    }
    ```
*   **Success Response (200 OK):**
    ```json
    {
      "success": true,
      "message": "Account deleted successfully."
    }
    ```
*   **Error Response (422 Unprocessable Content):**
    ```json
    {
      "success": false,
      "message": "Invalid password."
    }
    ```
*   **Validation Rules:**
    *   `password`: Required, String.
*   **Business Logic Explanation:**
    *   Authenticates user and confirms password matches via `Hash::check()`.
    *   If correct, deletes all active Sanctum tokens.
    *   If the user is a Driver, turns their status to offline (removing them from Redis coordinates) and deletes related driver profile data (documents, vehicles, bank accounts).
    *   Deletes saved addresses and wallets.
    *   Soft-deletes the `users` row. The global soft deletion scope prevents any future login attempts.
*   **Database Tables Affected:** `users`, `driver_profiles`, `driver_documents`, `driver_bank_accounts`, `vehicles`, `wallets`, `personal_access_tokens`
*   **Example Use Case:** Bob wants to stop using UEY. He inputs his password in the Account settings and deletes his account.

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
        "document_path": "driver_documents/PXIgMLGNcVZjFYbatUaRbb5rXRi46imbwkfji9EF.pdf",
        "view_url": "https://api.domain.com/api/v1/driver/documents/1/view",
        "download_url": "https://api.domain.com/api/v1/driver/documents/1/download",
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
            "document_path": "driver_documents/PXIgMLGNcVZjFYbatUaRbb5rXRi46imbwkfji9EF.pdf",
            "view_url": "https://api.domain.com/api/v1/driver/documents/1/view",
            "download_url": "https://api.domain.com/api/v1/driver/documents/1/download",
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
          "document_path": "driver_documents/PXIgMLGNcVZjFYbatUaRbb5rXRi46imbwkfji9EF.pdf",
          "view_url": "https://api.domain.com/api/v1/driver/documents/1/view",
          "download_url": "https://api.domain.com/api/v1/driver/documents/1/download",
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
        "document_path": "driver_documents/PXIgMLGNcVZjFYbatUaRbb5rXRi46imbwkfji9EF.pdf",
        "view_url": "https://api.domain.com/api/v1/driver/documents/1/view",
        "download_url": "https://api.domain.com/api/v1/driver/documents/1/download",
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

---

### 15a. View Driver Document
*   **API Name:** View Driver Document
*   **Purpose:** Streams a driver onboarding document directly inline to the browser/app. Only the document owner may access it.
*   **Endpoint URL:** `/driver/documents/{document}/view`
*   **HTTP Method:** `GET`
*   **Authentication Required:** Yes (Driver Only)
*   **Headers:**
    *   `Accept: application/json`
    *   `Authorization: Bearer {{auth_token}}`
*   **Parameters:**
    *   `document`: (Path parameter, Integer) The ID of the document to view.
*   **Request Payload:** None
*   **Success Response (200 OK):**
    *   *Streams file data inline with corresponding Content-Type headers (e.g. `application/pdf`).*
*   **Error Response (403 Forbidden - Unauthorized Access):**
    ```json
    {
      "success": false,
      "message": "Unauthorized."
    }
    ```
*   **Error Response (404 Not Found - File Missing):**
    ```json
    {
      "success": false,
      "message": "Document file not found."
    }
    ```
*   **Validation Rules:** None.
*   **Business Logic Explanation:**
    *   Authenticates driver and confirms they are the owner of the document.
    *   Checks if the physical document is stored on the local disk storage root.
    *   Streams the file inline using Symfony binary file response.
*   **Database Tables Affected:** `driver_documents` (reads)
*   **Example Use Case:** Bob taps on "View License" on his profile page to view his uploaded document.

---

### 15b. Download Driver Document
*   **API Name:** Download Driver Document
*   **Purpose:** Downloads a driver onboarding document. Only the document owner may access it.
*   **Endpoint URL:** `/driver/documents/{document}/download`
*   **HTTP Method:** `GET`
*   **Authentication Required:** Yes (Driver Only)
*   **Headers:**
    *   `Accept: application/json`
    *   `Authorization: Bearer {{auth_token}}`
*   **Parameters:**
    *   `document`: (Path parameter, Integer) The ID of the document to download.
*   **Request Payload:** None
*   **Success Response (200 OK):**
    *   *Downloads file data with attachment header and file name.*
*   **Error Response (403 Forbidden - Unauthorized Access):**
    ```json
    {
      "success": false,
      "message": "Unauthorized."
    }
    ```
*   **Error Response (404 Not Found - File Missing):**
    ```json
    {
      "success": false,
      "message": "Document file not found."
    }
    ```
*   **Validation Rules:** None.
*   **Business Logic Explanation:**
    *   Authenticates driver and confirms they are the owner of the document.
    *   Checks if the physical document is stored on the local disk storage root.
    *   Downloads the file using Laravel's storage download response.
*   **Database Tables Affected:** `driver_documents` (reads)
*   **Example Use Case:** Bob wants to save a backup of his uploaded vehicle registration on his local device.

---

## Module 3: Driver Availability & Live Location

### 16. Toggle Driver Status
*   **API Name:** Toggle Driver Status
*   **Purpose:** Enables an active, verified driver to toggle their availability online or offline.
*   **Endpoint URL:** `/driver/status`
*   **HTTP Method:** `POST`
*   **Authentication Required:** Yes (Driver Only)
*   **Headers:**
    *   `Accept: application/json`
    *   `Content-Type: application/json`
    *   `Authorization: Bearer {{auth_token}}`
*   **Request Payload (JSON):**
    ```json
    {
      "is_online": true
    }
    ```
*   **Success Response (200 OK):**
    ```json
    {
      "success": true,
      "message": "Driver status updated successfully.",
      "is_online": true
    }
    ```
*   **Error Response (403 Forbidden):**
    ```json
    {
      "success": false,
      "message": "Only active approved drivers can go online."
    }
    ```
*   **Validation Rules:**
    *   `is_online`: Required, Boolean.
*   **Business Logic Explanation:**
    *   Restricted to drivers only (requires `role:driver` Sanctum token ability).
    *   Checks if the driver's user status is `active` (e.g. they have passed all onboarding document checks). If they are still `pending_approval` or `suspended`, the API returns a 403 error.
    *   If `is_online` is `true`, the driver's ID and coordinates are indexed in Redis GEO under key `drivers:locations`.
    *   If `is_online` is `false`, the driver's entry is removed from the Redis GEO index.
*   **Database Tables Affected:** `driver_profiles` (updates `is_online`, `last_seen_at`)
*   **Frontend Flow:**
    1.  Driver toggles the online/offline switch on the main map.
    2.  Frontend makes a POST call to `/driver/status` with `is_online` status.
    3.  If successful, transitions app UI state and starts/stops background location tracking. If 403 is received, shows verification checklist dialog.
*   **Example Use Case:** Bob slides the status toggle to online. The app POSTs to `/driver/status`, updating Bob's status in Redis so riders can locate him.

---

### 17. Update Driver Location
*   **API Name:** Update Driver Location
*   **Purpose:** Updates the live location coordinates (latitude, longitude, bearing) for the authenticated driver. Synchronizes with Redis if the driver is online.
*   **Endpoint URL:** `/driver/location`
*   **HTTP Method:** `POST`
*   **Authentication Required:** Yes (Driver Only)
*   **Headers:**
    *   `Accept: application/json`
    *   `Content-Type: application/json`
    *   `Authorization: Bearer {{auth_token}}`
*   **Request Payload (JSON):**
    ```json
    {
      "current_latitude": 51.5204,
      "current_longitude": -0.1482,
      "bearing": 120.5
    }
    ```
*   **Success Response (200 OK):**
    ```json
    {
      "success": true,
      "message": "Driver location updated successfully."
    }
    ```
*   **Error Response (422 Unprocessable Content):**
    ```json
    {
      "success": false,
      "message": "The given data was invalid.",
      "errors": {
        "current_latitude": [
          "The current latitude must be between -90 and 90."
        ]
      }
    }
    ```
*   **Validation Rules:**
    *   `current_latitude`: Required, Numeric, must be between -90 and 90.
    *   `current_longitude`: Required, Numeric, must be between -180 and 180.
    *   `bearing`: Optional, Numeric, must be between 0 and 360.
*   **Business Logic Explanation:**
    *   Restricted to drivers only.
    *   Updates the driver's `current_latitude`, `current_longitude`, `bearing`, `last_located_at`, and `last_seen_at` fields in the database.
    *   If the driver is online (`is_online` is true), the updated coordinates are automatically synchronized with the Redis GEO index (`drivers:locations`) using `GEOADD`.
    *   If the driver is offline, updates are only saved to MySQL, and Redis syncing is skipped.
*   **Database Tables Affected:** `driver_profiles`
*   **Frontend Flow:**
    1.  App runs a background location service that tracks device GPS.
    2.  Every N seconds (e.g. 10s), if coordinates change significantly, frontend calls `/driver/location` with current lat, lng, and bearing.
*   **Example Use Case:** Bob drives down a street. The background service sends his coordinates to UEY, keeping his live pin location fresh on the rider's map.

---

### 18. Get Driver Dashboard
*   **API Name:** Get Driver Dashboard
*   **Purpose:** Retrieves driver dashboard details including profile summary, ratings, online status, and completed rides.
*   **Endpoint URL:** `/driver/dashboard`
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
      "dashboard": {
        "driver_profile_id": 1,
        "is_online": true,
        "rating": 4.85,
        "acceptance_rate": 97.2,
        "ontime_rate": 98.9,
        "completed_rides_count": 0,
        "earnings_summary": {
          "today": 0.0,
          "this_week": 0.0,
          "total": 0.0
        },
        "profile": {
          "name": "Bob Driver",
          "email": "bob.driver@example.com",
          "phone": "+447911999999",
          "avatar_url": null
        },
        "last_seen_at": "2026-06-23T19:12:00+00:00"
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
    *   Restricted to drivers only.
    *   Returns base statistics (`rating`, `acceptance_rate`, `ontime_rate`) alongside user details.
    *   `completed_rides_count` and `earnings_summary` represent placeholders that default to 0 and will be integrated with ride-hailing modules in future phases.
*   **Database Tables Affected:** `users`, `driver_profiles` (reads)
*   **Frontend Flow:**
    1.  Driver opens the main dashboard tab.
    2.  App sends a GET call to `/driver/dashboard`.
    3.  Renders performance stats, name, avatar, and placeholder earnings widgets.
*   **Example Use Case:** Bob checks his dashboard to see his rating (4.85) and check his current weekly earnings progress.

---

## Module 4: Ride Booking & Matching Engine

### 19. Estimate Fare
*   **API Name:** Estimate Fare
*   **Purpose:** Retrieves estimated distance, duration, and fare across all active vehicle types for a proposed ride.
*   **Endpoint URL:** `/rides/estimate`
*   **HTTP Method:** `POST`
*   **Authentication Required:** Yes (Rider Only)
*   **Headers:**
    *   `Accept: application/json`
    *   `Content-Type: application/json`
    *   `Authorization: Bearer {{auth_token}}`
*   **Request Payload (JSON):**
    ```json
    {
      "pickup_latitude": 51.5074,
      "pickup_longitude": -0.1278,
      "destination_latitude": 51.5204,
      "destination_longitude": -0.1482
    }
    ```
*   **Success Response (200 OK):**
    ```json
    {
      "success": true,
      "estimates": [
        {
          "vehicle_type_id": 1,
          "name": "Standard",
          "capacity": 4,
          "estimated_distance": 2.02,
          "estimated_duration": 4,
          "estimated_fare": 10.03
        },
        {
          "vehicle_type_id": 2,
          "name": "SUV",
          "capacity": 6,
          "estimated_distance": 2.02,
          "estimated_duration": 4,
          "estimated_fare": 19.05
        }
      ]
    }
    ```
*   **Error Response (422 Unprocessable Content):**
    ```json
    {
      "success": false,
      "message": "The given data was invalid.",
      "errors": {
        "pickup_latitude": [
          "The pickup latitude field is required."
        ]
      }
    }
    ```
*   **Validation Rules:**
    *   `pickup_latitude`: Required, Numeric, must be between -90 and 90.
    *   `pickup_longitude`: Required, Numeric, must be between -180 and 180.
    *   `destination_latitude`: Required, Numeric, must be between -90 and 90.
    *   `destination_longitude`: Required, Numeric, must be between -180 and 180.
*   **Business Logic Explanation:**
    *   Computes straight-line Haversine distance.
    *   Estimates duration using distance-based multiplier (e.g. 1.5 minutes per KM).
    *   Applies pricing factors (`base_fare`, `per_km_rate`, `per_minute_rate`, `minimum_fare`) defined on active `VehicleType` models to calculate estimated fare.
*   **Database Tables Affected:** `vehicle_types` (read)
*   **Frontend Flow:**
    1.  Rider enters pickup and destination addresses on screen.
    2.  Frontend gets coordinates via Google Places SDK/Map SDK.
    3.  Frontend invokes `/rides/estimate` with the coordinates.
    4.  Renders the list of vehicle categories, capacities, and pricing cards to let rider choose.
*   **Example Use Case:** Jane sets destination to Regent's Park. The app presents pricing: Standard (£10.03) and SUV (£19.05).

---

### 20. Request Ride
*   **API Name:** Request Ride
*   **Purpose:** Submits a new ride request, calculates estimated fare, creates a 6-digit OTP, and triggers geospatial matching with nearby online drivers.
*   **Endpoint URL:** `/rides/request`
*   **HTTP Method:** `POST`
*   **Authentication Required:** Yes (Rider Only)
*   **Headers:**
    *   `Accept: application/json`
    *   `Content-Type: application/json`
    *   `Authorization: Bearer {{auth_token}}`
*   **Request Payload (JSON):**
    ```json
    {
      "pickup_latitude": 51.5074,
      "pickup_longitude": -0.1278,
      "pickup_address": "London Eye, London",
      "destination_latitude": 51.5204,
      "destination_longitude": -0.1482,
      "destination_address": "Regent's Park, London",
      "vehicle_type_id": 1
    }
    ```
*   **Success Response (201 Created):**
    ```json
    {
      "success": true,
      "message": "Ride requested successfully.",
      "ride": {
        "id": 1,
        "rider_id": 10,
        "driver_profile_id": null,
        "vehicle_type_id": 1,
        "pickup_address": "London Eye, London",
        "pickup_latitude": 51.5074,
        "pickup_longitude": -0.1278,
        "destination_address": "Regent's Park, London",
        "destination_latitude": 51.5204,
        "destination_longitude": -0.1482,
        "status": "pending",
        "otp": "483920",
        "estimated_distance": 2.02,
        "estimated_duration": 4,
        "estimated_fare": 10.03,
        "created_at": "2026-06-24T01:45:00+00:00",
        "updated_at": "2026-06-24T01:45:00+00:00"
      }
    }
    ```
*   **Error Response (422 Unprocessable Content):**
    ```json
    {
      "success": false,
      "message": "The given data was invalid.",
      "errors": {
        "vehicle_type_id": [
          "The selected vehicle type id is invalid."
        ]
      }
    }
    ```
*   **Validation Rules:**
    *   `pickup_latitude`, `pickup_longitude`: Required, Numeric, valid boundaries.
    *   `pickup_address`: Required, String.
    *   `destination_latitude`, `destination_longitude`: Required, Numeric, valid boundaries.
    *   `destination_address`: Required, String.
    *   `vehicle_type_id`: Required, exists in `vehicle_types` table.
*   **Business Logic Explanation:**
    *   Executes in a DB transaction.
    *   Finds target vehicle type, calculates distance/duration/fare estimates, and generates a random 6-digit OTP code.
    *   Creates a `Ride` row in status `pending`.
    *   Invokes the matching engine, which fetches drivers within matching radius (default 5.0 KM) from Redis GEO index (or database-based fallback).
    *   Creates a `RideRequest` in status `pending` with a 30-second expiry for every eligible nearby online driver who is not already on an active trip and has an approved vehicle matching the category.
*   **Database Tables Affected:** `rides`, `ride_requests`, `ride_status_logs`
*   **Frontend Flow:**
    1.  Rider selects a vehicle category and taps "Confirm Booking".
    2.  Frontend fires `POST /rides/request`.
    3.  On 201 response, shows a loader screen with "Finding a Driver...".
*   **Example Use Case:** Jane requests a Standard ride. The backend generates matching requests for nearby drivers Bob and Alice.

---

### 21. Cancel Ride
*   **API Name:** Cancel Ride
*   **Purpose:** Rider or driver cancels a ride before the trip has officially started.
*   **Endpoint URL:** `/rides/{ride}/cancel`
*   **HTTP Method:** `POST`
*   **Authentication Required:** Yes
*   **Headers:**
    *   `Accept: application/json`
    *   `Content-Type: application/json`
    *   `Authorization: Bearer {{auth_token}}`
*   **Request Payload (JSON):**
    ```json
    {
      "cancel_reason": "Decided to take the train instead"
    }
    ```
*   **Success Response (200 OK):**
    ```json
    {
      "success": true,
      "message": "Ride cancelled successfully.",
      "ride": {
        "id": 1,
        "status": "cancelled",
        "cancelled_by": "rider",
        "cancel_reason": "Decided to take the train instead",
        "cancelled_at": "2026-06-24T01:47:00+00:00"
      }
    }
    ```
*   **Error Response (422 Unprocessable Content):**
    ```json
    {
      "success": false,
      "message": "Cancellation is forbidden once the ride has started, completed, or is already cancelled."
    }
    ```
*   **Validation Rules:**
    *   `cancel_reason`: Optional, String, max 255.
*   **Business Logic Explanation:**
    *   Cancellations are allowed only when status is `pending`, `accepted`, `arriving`, or `arrived`.
    *   Once a ride status is `in_progress` (passenger on board), `completed`, or already `cancelled`, cancellation is rejected.
    *   Marks all pending `RideRequest` rows for this ride as `expired` so that drivers no longer see the offers.
*   **Database Tables Affected:** `rides`, `ride_requests`, `ride_status_logs`
*   **Frontend Flow:**
    1.  Rider taps "Cancel Ride" in active map view.
    2.  App asks for cancellation reason.
    3.  Frontend submits POST.
    4.  App resets map back to the main booking screen.
*   **Example Use Case:** Jane cancels the ride 1 minute after booking because she realized she forgot her keys.

---

### 22. Get Ride Details
*   **API Name:** Get Ride Details
*   **Purpose:** Fetches the full profile, status, and pricing details of a specific ride.
*   **Endpoint URL:** `/rides/{ride}`
*   **HTTP Method:** `GET`
*   **Authentication Required:** Yes
*   **Headers:**
    *   `Accept: application/json`
    *   `Authorization: Bearer {{auth_token}}`
*   **Request Payload:** None
*   **Success Response (200 OK):**
    ```json
    {
      "success": true,
      "ride": {
        "id": 1,
        "status": "accepted",
        "otp": "483920",
        "estimated_fare": 9.48,
        "pickup_address": "London Eye, London",
        "destination_address": "Regent's Park, London",
        "driver_profile_id": 3
      }
    }
    ```
*   **Error Response (404 Not Found):**
    ```json
    {
      "message": "Record not found."
    }
    ```
*   **Validation Rules:** None.
*   **Business Logic Explanation:**
    *   Retrieves specific ride log information, including nested rider and driver profile records.
*   **Database Tables Affected:** `rides`
*   **Frontend Flow:**
    1.  User clicks on notification about a ride status update.
    2.  Frontend fetches details from `/rides/{id}`.
    3.  Updates maps, driver badges, and OTP widgets.
*   **Example Use Case:** Jane opens her active trip screen. The app fetches ride details to show the assigned driver's profile (name, rating, vehicle details).

---

### 23. Rider Ride History
*   **API Name:** Rider Ride History
*   **Purpose:** Returns a list of past and active rides requested by the authenticated rider.
*   **Endpoint URL:** `/rides`
*   **HTTP Method:** `GET`
*   **Authentication Required:** Yes (Rider Only)
*   **Headers:**
    *   `Accept: application/json`
    *   `Authorization: Bearer {{auth_token}}`
*   **Request Payload:** None
*   **Success Response (200 OK):**
    ```json
    {
      "success": true,
      "rides": [
        {
          "id": 1,
          "pickup_address": "London Eye",
          "destination_address": "Regent Park",
          "status": "cancelled",
          "estimated_fare": 9.48,
          "created_at": "2026-06-24T01:45:00+00:00"
        }
      ]
    }
    ```
*   **Business Logic Explanation:**
    *   Queries rides table where `rider_id = user_id`, ordered newest first.
*   **Database Tables Affected:** `rides`
*   **Frontend Flow:**
    1.  Rider navigates to "My Rides" / "Trip History".
    2.  Queries `GET /rides`.
    3.  Renders listing cards.
*   **Example Use Case:** Jane opens history to review her travel expenditures.

---

### 24. Get Rider Active Ride
*   **API Name:** Get Rider Active Ride
*   **Purpose:** Retrieves details of the rider's current active trip (status is not completed or cancelled).
*   **Endpoint URL:** `/rides/active`
*   **HTTP Method:** `GET`
*   **Authentication Required:** Yes (Rider Only)
*   **Headers:**
    *   `Accept: application/json`
    *   `Authorization: Bearer {{auth_token}}`
*   **Success Response (200 OK):**
    ```json
    {
      "success": true,
      "ride": {
        "id": 1,
        "status": "accepted",
        "driver_profile_id": 3
      }
    }
    ```
*   **Error Response (404 Not Found):**
    ```json
    {
      "success": false,
      "message": "No active ride found."
    }
    ```
*   **Business Logic Explanation:**
    *   Queries the `rides` table for the rider's latest record with a status other than `completed` or `cancelled`.
*   **Database Tables Affected:** `rides`
*   **Example Use Case:** Jane restarts her phone during a trip. Upon launching the UEY app, it calls `/rides/active` to instantly restore her active trip map view.

---

### 25. Get Pending Ride Requests
*   **API Name:** Get Pending Ride Requests
*   **Purpose:** Retrieves active, pending trip offers broadcasted to the online driver.
*   **Endpoint URL:** `/driver/ride-requests`
*   **HTTP Method:** `GET`
*   **Authentication Required:** Yes (Driver Only)
*   **Headers:**
    *   `Accept: application/json`
    *   `Authorization: Bearer {{auth_token}}`
*   **Success Response (200 OK):**
    ```json
    {
      "success": true,
      "requests": [
        {
          "id": 1,
          "ride_id": 1,
          "driver_profile_id": 3,
          "status": "pending",
          "expires_at": "2026-06-24T01:45:30+00:00",
          "ride": {
            "id": 1,
            "pickup_address": "London Eye",
            "destination_address": "Regent Park",
            "estimated_fare": 9.48
          }
        }
      ]
    }
    ```
*   **Business Logic Explanation:**
    *   Expires any pending offers whose `expires_at` timestamp is in the past.
    *   Returns only active `pending` requests assigned to this driver.
*   **Database Tables Affected:** `ride_requests`
*   **Frontend Flow:**
    1.  Driver app is online.
    2.  Polls `/driver/ride-requests` or receives a push notification.
    3.  Renders matching offer overlay on map with a circular countdown timer.
*   **Example Use Case:** Bob receives a popup on screen indicating a customer wants standard transport 1.2 KM away.

---

### 26. Accept Ride Request
*   **API Name:** Accept Ride Request
*   **Purpose:** Driver accepts a trip request. Row locks protect against race conditions.
*   **Endpoint URL:** `/driver/ride-requests/{request}/accept`
*   **HTTP Method:** `POST`
*   **Authentication Required:** Yes (Driver Only)
*   **Headers:**
    *   `Accept: application/json`
    *   `Authorization: Bearer {{auth_token}}`
*   **Success Response (200 OK):**
    ```json
    {
      "success": true,
      "message": "Ride request accepted successfully.",
      "ride": {
        "id": 1,
        "status": "accepted",
        "driver_profile_id": 3,
        "accepted_at": "2026-06-24T01:45:10+00:00"
      }
    }
    ```
*   **Error Response (422 Unprocessable Content):**
    ```json
    {
      "success": false,
      "message": "Ride request is no longer available."
    }
    ```
*   **Business Logic Explanation:**
    *   Executes in a DB transaction with **`lockForUpdate()`** on the `Ride` row.
    *   Verifies that the ride status is still `pending` (i.e. not already accepted by another driver).
    *   Updates request status to `accepted`, sets other drivers' matching requests to `expired`, assigns driver profile ID, and sets ride status to `accepted`.
*   **Database Tables Affected:** `rides`, `ride_requests`, `ride_status_logs`
*   **Example Use Case:** Bob taps "Accept". Because he was first, he is assigned the ride. When Alice (who received the same broadcast) tries to accept a second later, she gets a 422 error informing her the ride is gone.

---

### 27. Decline Ride Request
*   **API Name:** Decline Ride Request
*   **Purpose:** Driver declines a matching offer, removing it from their queue.
*   **Endpoint URL:** `/driver/ride-requests/{request}/decline`
*   **HTTP Method:** `POST`
*   **Authentication Required:** Yes (Driver Only)
*   **Headers:**
    *   `Accept: application/json`
    *   `Authorization: Bearer {{auth_token}}`
*   **Success Response (200 OK):**
    ```json
    {
      "success": true,
      "message": "Ride request declined successfully."
    }
    ```
*   **Business Logic Explanation:**
    *   Sets the status of this driver's request offer to `declined`.
*   **Database Tables Affected:** `ride_requests`
*   **Example Use Case:** Bob declines the ride because he wants to take a break.

---

### 28. Get Driver Active Ride
*   **API Name:** Get Driver Active Ride
*   **Purpose:** Fetches the driver's current assigned active trip.
*   **Endpoint URL:** `/driver/active-ride`
*   **HTTP Method:** `GET`
*   **Authentication Required:** Yes (Driver Only)
*   **Headers:**
    *   `Accept: application/json`
    *   `Authorization: Bearer {{auth_token}}`
*   **Success Response (200 OK):**
    ```json
    {
      "success": true,
      "ride": {
        "id": 1,
        "status": "accepted",
        "driver_profile_id": 3
      }
    }
    ```
*   **Error Response (404 Not Found):**
    ```json
    {
      "success": false,
      "message": "No active ride found."
    }
    ```
*   **Database Tables Affected:** `rides`
*   **Example Use Case:** Bob opens the navigation dashboard. The app queries `/driver/active-ride` to render navigation instructions and passenger details.

---

## Module 6: Ride Lifecycle Management & Trip Execution

### 29. Get Ride Details (Driver)
*   **API Name:** Get Ride Details (Driver)
*   **Purpose:** Retrieves details of a ride. Accessible only by the assigned driver.
*   **Endpoint URL:** `/driver/rides/{ride}`
*   **HTTP Method:** `GET`
*   **Authentication Required:** Yes (Driver Only)
*   **Headers:**
    *   `Accept: application/json`
    *   `Authorization: Bearer {{auth_token}}`
*   **Success Response (200 OK):**
    ```json
    {
      "success": true,
      "ride": {
        "id": 2,
        "rider_id": 1,
        "driver_profile_id": 3,
        "vehicle_type_id": 1,
        "pickup_address": "London Eye",
        "pickup_latitude": 51.5074,
        "pickup_longitude": -0.1278,
        "destination_address": "Regent Park",
        "destination_latitude": 51.5204,
        "destination_longitude": -0.1482,
        "status": "accepted",
        "otp": "123456",
        "estimated_distance": 2.0,
        "estimated_duration": 5,
        "estimated_fare": 10.0,
        "actual_distance": null,
        "actual_duration": null,
        "actual_fare": null,
        "accepted_at": "2026-06-26T13:19:32+05:30",
        "arrived_at": null,
        "started_at": null,
        "completed_at": null,
        "cancelled_at": null,
        "otp_verified_at": null,
        "otp_verified_by": null,
        "fare_breakdown": null
      }
    }
    ```
*   **Error Response (403 Forbidden):**
    ```json
    {
      "success": false,
      "message": "You are not authorized to view this ride."
    }
    ```
*   **Database Tables Affected:** `rides`
*   **Example Use Case:** Bob opens the details page for his accepted ride to check coordinates, passenger name, and route details.

---

### 30. Mark Ride as Arriving
*   **API Name:** Mark Ride as Arriving
*   **Purpose:** Transition the ride status from accepted to arriving.
*   **Endpoint URL:** `/driver/rides/{ride}/arriving`
*   **HTTP Method:** `POST`
*   **Authentication Required:** Yes (Driver Only)
*   **Headers:**
    *   `Accept: application/json`
    *   `Authorization: Bearer {{auth_token}}`
*   **Success Response (200 OK):**
    ```json
    {
      "success": true,
      "message": "Ride status updated to arriving.",
      "ride": {
        "id": 2,
        "status": "arriving"
      }
    }
    ```
*   **Error Response (422 Unprocessable Content):**
    ```json
    {
      "success": false,
      "message": "The given data was invalid.",
      "errors": {
        "status": [
          "Invalid transition from pending to arriving."
        ]
      }
    }
    ```
*   **Business Logic Explanation:**
    *   Ensures that only the assigned driver is updating the status.
    *   Enforces sequence transitions. Transitions are only valid if the current status is `accepted`.
*   **Database Tables Affected:** `rides`, `ride_status_logs`

---

### 31. Mark Ride as Arrived
*   **API Name:** Mark Ride as Arrived
*   **Purpose:** Transition the ride status from arriving to arrived. Sets the arrived_at timestamp.
*   **Endpoint URL:** `/driver/rides/{ride}/arrived`
*   **HTTP Method:** `POST`
*   **Authentication Required:** Yes (Driver Only)
*   **Headers:**
    *   `Accept: application/json`
    *   `Authorization: Bearer {{auth_token}}`
*   **Success Response (200 OK):**
    ```json
    {
      "success": true,
      "message": "Ride status updated to arrived.",
      "ride": {
        "id": 2,
        "status": "arrived",
        "arrived_at": "2026-06-26T13:22:00+05:30"
      }
    }
    ```
*   **Business Logic Explanation:**
    *   Ensures that only the assigned driver is updating the status.
    *   Transitions are only valid if the current status is `arriving`. Sets the `arrived_at` timestamp to the current time.
*   **Database Tables Affected:** `rides`, `ride_status_logs`

---

### 32. Start Ride (Verify OTP)
*   **API Name:** Start Ride
*   **Purpose:** Transition the ride status from arrived to in_progress. Verifies the rider's 6-digit OTP. Sets started_at and otp_verified_at.
*   **Endpoint URL:** `/driver/rides/{ride}/start`
*   **HTTP Method:** `POST`
*   **Authentication Required:** Yes (Driver Only)
*   **Headers:**
    *   `Accept: application/json`
    *   `Content-Type: application/json`
    *   `Authorization: Bearer {{auth_token}}`
*   **Request Payload:**
    ```json
    {
      "otp": "123456"
    }
    ```
*   **Success Response (200 OK):**
    ```json
    {
      "success": true,
      "message": "Ride started successfully.",
      "ride": {
        "id": 2,
        "status": "in_progress",
        "started_at": "2026-06-26T13:24:00+05:30",
        "otp_verified_at": "2026-06-26T13:24:00+05:30",
        "otp_verified_by": 3
      }
    }
    ```
*   **Error Response (422 Unprocessable Content - Wrong OTP):**
    ```json
    {
      "success": false,
      "message": "The given data was invalid.",
      "errors": {
        "otp": [
          "The provided OTP is invalid."
        ]
      }
    }
    ```
*   **Business Logic Explanation:**
    *   Verifies that the provided OTP matches the ride's generated OTP.
    *   Sets `otp_verified_at` and `started_at` to the current timestamp.
    *   Sets `otp_verified_by` to the ID of the verifying driver.
    *   Transitions status from `arrived` to `in_progress`.
*   **Database Tables Affected:** `rides`, `ride_status_logs`

---

### 33. Complete Ride
*   **API Name:** Complete Ride
*   **Purpose:** Transition the ride status from in_progress to completed. Computes actual fare and stores actual trip metrics and fare breakdown.
*   **Endpoint URL:** `/driver/rides/{ride}/complete`
*   **HTTP Method:** `POST`
*   **Authentication Required:** Yes (Driver Only)
*   **Headers:**
    *   `Accept: application/json`
    *   `Content-Type: application/json`
    *   `Authorization: Bearer {{auth_token}}`
*   **Request Payload:**
    ```json
    {
      "actual_distance": 3.5,
      "actual_duration": 10
    }
    ```
*   **Success Response (200 OK):**
    ```json
    {
      "success": true,
      "message": "Ride completed successfully.",
      "ride": {
        "id": 2,
        "status": "completed",
        "actual_distance": 3.5,
        "actual_duration": 10,
        "actual_fare": 15.25,
        "completed_at": "2026-06-26T13:34:00+05:30",
        "fare_breakdown": {
          "base_fare": 5.00,
          "distance": 3.5,
          "per_km_rate": 1.50,
          "distance_fare": 5.25,
          "duration": 10,
          "per_minute_rate": 0.50,
          "duration_fare": 5.00,
          "calculated_fare": 15.25,
          "minimum_fare": 7.00,
          "applied_minimum_fare": false,
          "final_fare": 15.25
        }
      },
      "payment": {
        "id": 7,
        "payment_method": "wallet",
        "payment_status": "paid"
      }
    }
    ```
*   **Error Response (422 Unprocessable Content - Insufficient Wallet Balance):**
    ```json
    {
      "success": false,
      "message": "Insufficient wallet balance.",
      "wallet_balance": 5.00,
      "required_amount": 20.00,
      "shortfall": 15.00
    }
    ```
*   **Business Logic Explanation:**
    *   Validates input values (distance >= 0, duration >= 0).
    *   Calculates final fare: `base_fare + per_km_rate * actual_distance + per_minute_rate * actual_duration`, capped at the category's `minimum_fare`.
    *   Saves the detailed invoice items under `fare_breakdown` JSON column.
    *   Triggers payment processing. If payment method is wallet, checks if rider balance >= total fare. On failure, aborts with 422, rolls back the entire completed status, and logs a failed payment.
    *   Updates the driver's location coordinate fields and coordinates inside Redis to the destination of the ride to mark availability nearby.
*   **Database Tables Affected:** `rides`, `driver_profiles`, `ride_status_logs`, `payments`, `wallets`, `wallet_transactions`

---

### 34. Get Ride Payment Details
*   **API Name:** Get Ride Payment Details
*   **Purpose:** Retrieves payment logs and commission details for a specific ride. Access is restricted to the rider or driver associated with the ride.
*   **Endpoint URL:** `/payments/{ride}`
*   **HTTP Method:** `GET`
*   **Authentication Required:** Yes (Rider or Driver)
*   **Headers:**
    *   `Accept: application/json`
    *   `Authorization: Bearer {{auth_token}}`
*   **Parameters:**
    *   `ride`: (Path parameter, Integer) The ID of the ride.
*   **Success Response (200 OK):**
    ```json
    {
      "success": true,
      "payment": {
        "id": 7,
        "ride_id": 12,
        "rider_id": 1,
        "driver_profile_id": 3,
        "payment_method": "wallet",
        "payment_status": "paid",
        "transaction_reference": "PAY-20260704-000007",
        "subtotal": 15.00,
        "tax": 0.00,
        "discount": 0.00,
        "platform_commission": 2.25,
        "driver_earning": 12.75,
        "total": 15.00,
        "paid_at": "2026-07-04T00:30:00+05:30",
        "created_at": "2026-07-04T00:29:00+05:30",
        "updated_at": "2026-07-04T00:30:00+05:30"
      }
    }
    ```
*   **Error Response (403 Forbidden - Unauthorized Access):**
    ```json
    {
      "success": false,
      "message": "Unauthorized."
    }
    ```
*   **Business Logic Explanation:**
    *   Verifies that the requesting user is the rider or driver of the ride.
    *   Fetches the matching payment record and returns the detailed attributes.
*   **Database Tables Affected:** `payments` (reads)

---

### 35. Get Payment History
*   **API Name:** Get Payment History
*   **Purpose:** Retrieves payment history logs. For Riders, returns rides they paid for. For Drivers, returns their earnings and commission logs.
*   **Endpoint URL:** `/payments/history`
*   **HTTP Method:** `GET`
*   **Authentication Required:** Yes (Rider or Driver)
*   **Headers:**
    *   `Accept: application/json`
    *   `Authorization: Bearer {{auth_token}}`
*   **Parameters (Query Parameters):**
    *   `page`: (Optional, Integer) The page number for pagination. Default is `1`.
    *   `per_page`: (Optional, Integer) The number of items per page. Default is `15`.
    *   `status`: (Optional, String) Filter by payment status (e.g., `paid`, `failed`, `pending`).
    *   `payment_method`: (Optional, String) Filter by payment method (e.g., `cash`, `wallet`, `stripe`).
    *   `from`: (Optional, Date string Y-m-d) Filter payments created on or after this date.
    *   `to`: (Optional, Date string Y-m-d) Filter payments created on or before this date.
*   **Success Response (200 OK):**
    ```json
    {
      "success": true,
      "payments": [
        {
          "id": 7,
          "ride_id": 12,
          "rider_id": 1,
          "driver_profile_id": 3,
          "payment_method": "wallet",
          "payment_status": "paid",
          "transaction_reference": "PAY-20260704-000007",
          "subtotal": 15.00,
          "tax": 0.00,
          "discount": 0.00,
          "platform_commission": 2.25,
          "driver_earning": 12.75,
          "total": 15.00,
          "paid_at": "2026-07-04T00:30:00+05:30",
          "created_at": "2026-07-04T00:29:00+05:30",
          "updated_at": "2026-07-04T00:30:00+05:30"
        }
      ],
      "meta": {
        "current_page": 1,
        "per_page": 15,
        "total": 1,
        "last_page": 1
      }
    }
    ```
*   **Business Logic Explanation:**
    *   Queries payment records by either `rider_id` or `driver_profile_id` depending on the user's role.
    *   Applies optional query parameters to filter status, method, and date ranges.
    *   Returns the payment list sorted in descending order of ID (newest first) with pagination support.
*   **Database Tables Affected:** `payments` (reads)

---

### 36. Get Ride Invoice Details
*   **API Name:** Get Ride Invoice Details
*   **Purpose:** Generates a complete structured receipt/invoice breakdown for a completed ride. Includes ride details and payment logs.
*   **Endpoint URL:** `/payments/invoice/{ride}`
*   **HTTP Method:** `GET`
*   **Authentication Required:** Yes (Rider or Driver)
*   **Headers:**
    *   `Accept: application/json`
    *   `Authorization: Bearer {{auth_token}}`
*   **Parameters:**
    *   `ride`: (Path parameter, Integer) The ID of the ride.
*   **Success Response (200 OK):**
    ```json
    {
      "success": true,
      "invoice": {
        "ride_id": 12,
        "pickup_address": "London Eye",
        "destination_address": "Regents Park",
        "distance": 3.5,
        "duration": 10,
        "payment_method": "wallet",
        "payment_status": "paid",
        "transaction_reference": "PAY-20260704-000007",
        "completed_at": "2026-07-04T00:30:00+05:30",
        "rider": {
          "id": 1,
          "name": "Alice Rider"
        },
        "driver": {
          "id": 3,
          "name": "Bob Driver"
        },
        "fare_breakdown": {
          "subtotal": 15.00,
          "tax": 0.00,
          "discount": 0.00,
          "platform_commission": 2.25,
          "driver_earning": 12.75,
          "total": 15.00
        },
        "paid_at": "2026-07-04T00:30:00+05:30"
      }
    }
    ```
*   **Error Response (403 Forbidden - Unauthorized Access):**
    ```json
    {
      "success": false,
      "message": "Unauthorized."
    }
    ```
*   **Database Tables Affected:** `rides`, `payments`, `users` (reads)

---

### 37. Submit Ride Review
*   **API Name:** Submit Ride Review
*   **Purpose:** Submits a rating and optional review for a completed ride. Only participants of the ride can rate the other party once. Reviews cannot be edited or deleted after submission.
*   **Endpoint URL:** `/rides/{ride}/review`
*   **HTTP Method:** `POST`
*   **Authentication Required:** Yes (Rider or Driver)
*   **Headers:**
    *   `Accept: application/json`
    *   `Content-Type: application/json`
    *   `Authorization: Bearer {{auth_token}}`
*   **Request Payload:**
    ```json
    {
      "rating": 5,
      "review": "Polite driver and clean vehicle.",
      "review_tags": ["polite", "clean_car"],
      "is_anonymous": false
    }
    ```
*   **Success Response (201 Created):**
    ```json
    {
      "success": true,
      "message": "Review submitted successfully.",
      "review": {
        "id": 1,
        "ride_id": 12,
        "reviewer_id": 1,
        "reviewee_id": 3,
        "rating": 5,
        "review": "Polite driver and clean vehicle.",
        "review_tags": ["polite", "clean_car"],
        "is_anonymous": false,
        "created_at": "2026-07-04T22:30:00+05:30",
        "updated_at": "2026-07-04T22:30:00+05:30"
      },
      "reviewee_stats": {
        "average_rating": 4.85,
        "total_reviews": 12
      }
    }
    ```
*   **Business Logic Explanation:**
    *   Validates that the ride status is completed.
    *   Ensures that only participants (rider or driver) of the ride can submit reviews.
    *   Prevents duplicate reviews for the same ride by the same participant.
    *   Updates the reviewee's average rating and total review counts incrementally.
*   **Database Tables Affected:** `ride_reviews`, `users`, `driver_profiles`

---

### 38. Get Ride Reviews
*   **API Name:** Get Ride Reviews
*   **Purpose:** Retrieves both reviews (rider-to-driver and driver-to-rider) submitted for a specific ride. Restricted to the ride participants.
*   **Endpoint URL:** `/rides/{ride}/review`
*   **HTTP Method:** `GET`
*   **Authentication Required:** Yes (Rider or Driver participant)
*   **Headers:**
    *   `Accept: application/json`
    *   `Authorization: Bearer {{auth_token}}`
*   **Success Response (200 OK):**
    ```json
    {
      "success": true,
      "rider_review": {
        "id": 1,
        "ride_id": 12,
        "reviewer_id": 1,
        "reviewee_id": 3,
        "rating": 5,
        "review": "Polite driver and clean vehicle.",
        "review_tags": ["polite", "clean_car"],
        "is_anonymous": false,
        "created_at": "2026-07-04T22:30:00+05:30",
        "updated_at": "2026-07-04T22:30:00+05:30"
      },
      "driver_review": null
    }
    ```
*   **Business Logic Explanation:**
    *   Authorizes that the requester is a participant of the ride.
    *   Returns the rider's review and the driver's review associated with the ride.
*   **Database Tables Affected:** `ride_reviews` (reads)

---

### 39. Get Driver Reviews
*   **API Name:** Get Driver Reviews
*   **Purpose:** Retrieves a paginated list of reviews received by a driver. Supports sorting and page/per_page parameters. Returns `reviewer_name` instead of `reviewer_id` (returns `"Anonymous"` if `is_anonymous` is true).
*   **Endpoint URL:** `/drivers/{driver}/reviews`
*   **HTTP Method:** `GET`
*   **Authentication Required:** Yes
*   **Headers:**
    *   `Accept: application/json`
    *   `Authorization: Bearer {{auth_token}}`
*   **Parameters (Query Parameters):**
    *   `page`: (Optional, Integer) The page number. Default `1`.
    *   `per_page`: (Optional, Integer) Items per page. Default `15`.
    *   `sort`: (Optional, String) Sorting method: `latest`, `highest_rating`, `lowest_rating`. Default `latest`.
*   **Success Response (200 OK):**
    ```json
    {
      "success": true,
      "reviews": [
        {
          "id": 1,
          "ride_id": 12,
          "reviewer_name": "Alice Rider",
          "reviewee_id": 3,
          "rating": 5,
          "review": "Polite driver and clean vehicle.",
          "review_tags": ["polite", "clean_car"],
          "is_anonymous": false,
          "created_at": "2026-07-04T22:30:00+05:30",
          "updated_at": "2026-07-04T22:30:00+05:30"
        }
      ],
      "rating_summary": {
        "average_rating": 4.85,
        "total_reviews": 12,
        "five_star": 10,
        "four_star": 2,
        "three_star": 0,
        "two_star": 0,
        "one_star": 0
      },
      "meta": {
        "current_page": 1,
        "per_page": 15,
        "total": 1,
        "last_page": 1
      },
      "links": {
        "first": "https://api.domain.com/api/v1/drivers/3/reviews?page=1",
        "last": "https://api.domain.com/api/v1/drivers/3/reviews?page=1",
        "prev": null,
        "next": null
      }
    }
    ```
*   **Database Tables Affected:** `ride_reviews` (reads), `users` (reads)

---

### 40. Get Rider Reviews
*   **API Name:** Get Rider Reviews
*   **Purpose:** Retrieves a paginated list of reviews received by a rider. Supports sorting and page/per_page parameters. Returns `reviewer_name` instead of `reviewer_id` (returns `"Anonymous"` if `is_anonymous` is true).
*   **Endpoint URL:** `/riders/{rider}/reviews`
*   **HTTP Method:** `GET`
*   **Authentication Required:** Yes
*   **Headers:**
    *   `Accept: application/json`
    *   `Authorization: Bearer {{auth_token}}`
*   **Parameters (Query Parameters):**
    *   `page`: (Optional, Integer) The page number. Default `1`.
    *   `per_page`: (Optional, Integer) Items per page. Default `15`.
    *   `sort`: (Optional, String) Sorting method: `latest`, `highest_rating`, `lowest_rating`. Default `latest`.
*   **Success Response (200 OK):**
    ```json
    {
      "success": true,
      "reviews": [
        {
          "id": 2,
          "ride_id": 12,
          "reviewer_name": "Anonymous",
          "reviewee_id": 1,
          "rating": 4,
          "review": "Nice passenger.",
          "review_tags": null,
          "is_anonymous": true,
          "created_at": "2026-07-04T22:35:00+05:30",
          "updated_at": "2026-07-04T22:35:00+05:30"
        }
      ],
      "rating_summary": {
        "average_rating": 4.00,
        "total_reviews": 1,
        "five_star": 0,
        "four_star": 1,
        "three_star": 0,
        "two_star": 0,
        "one_star": 0
      },
      "meta": {
        "current_page": 1,
        "per_page": 15,
        "total": 1,
        "last_page": 1
      },
      "links": {
        "first": "https://api.domain.com/api/v1/riders/1/reviews?page=1",
        "last": "https://api.domain.com/api/v1/riders/1/reviews?page=1",
        "prev": null,
        "next": null
      }
    }
    ```
*   **Database Tables Affected:** `ride_reviews` (reads), `users` (reads)

---

### 41. Get Wallet Balance
*   **API Name:** Get Wallet Balance
*   **Purpose:** Retrieves current wallet balance, currency, and details of the last transaction.
*   **Endpoint URL:** `/wallet`
*   **HTTP Method:** `GET`
*   **Authentication Required:** Yes
*   **Headers:**
    *   `Accept: application/json`
    *   `Authorization: Bearer {{auth_token}}`
*   **Success Response (200 OK):**
    ```json
    {
      "success": true,
      "wallet": {
        "balance": 150.00,
        "currency": "USD",
        "last_transaction": {
          "id": 12,
          "transaction_type": "top_up",
          "type": "credit",
          "amount": 50.00,
          "balance_before": 100.00,
          "balance_after": 150.00,
          "status": "completed",
          "reference": "topup_3",
          "remarks": "Stripe wallet top-up completed",
          "created_at": "2026-07-05T21:30:00+00:00"
        },
        "updated_at": "2026-07-05T21:30:00+00:00"
      }
    }
    ```
*   **Database Tables Affected:** `wallets` (reads), `wallet_transactions` (reads)

---

### 42. Get Wallet Transactions
*   **API Name:** Get Wallet Transactions
*   **Purpose:** Retrieves a paginated list of ledger transactions. Supports sorting and pagination.
*   **Endpoint URL:** `/wallet/transactions`
*   **HTTP Method:** `GET`
*   **Authentication Required:** Yes
*   **Headers:**
    *   `Accept: application/json`
    *   `Authorization: Bearer {{auth_token}}`
*   **Parameters (Query Parameters):**
    *   `page`: (Optional, Integer) Page number. Default `1`.
    *   `per_page`: (Optional, Integer) Items per page. Default `15`.
    *   `sort`: (Optional, String) Sorting method: `latest`, `oldest`. Default `latest`.
*   **Success Response (200 OK):**
    ```json
    {
      "success": true,
      "transactions": [
        {
          "id": 12,
          "transaction_type": "top_up",
          "type": "credit",
          "amount": 50.00,
          "balance_before": 100.00,
          "balance_after": 150.00,
          "status": "completed",
          "reference": "topup_3",
          "remarks": "Stripe wallet top-up completed",
          "created_at": "2026-07-05T21:30:00+00:00"
        }
      ],
      "meta": {
        "current_page": 1,
        "per_page": 15,
        "total": 1,
        "last_page": 1
      },
      "links": {
        "first": "https://api.domain.com/api/v1/wallet/transactions?page=1",
        "last": "https://api.domain.com/api/v1/wallet/transactions?page=1",
        "prev": null,
        "next": null
      }
    }
    ```
*   **Database Tables Affected:** `wallet_transactions` (reads)

---

### 43. Create Top-up PaymentIntent
*   **API Name:** Create Top-up PaymentIntent
*   **Purpose:** Requests creation of a Stripe PaymentIntent to initiate a wallet top-up. The frontend confirms this intent using the Stripe SDK.
*   **Endpoint URL:** `/wallet/top-up`
*   **HTTP Method:** `POST`
*   **Authentication Required:** Yes
*   **Headers:**
    *   `Accept: application/json`
    *   `Content-Type: application/json`
    *   `Authorization: Bearer {{auth_token}}`
*   **Request Payload:**
    ```json
    {
      "amount": 50.00
    }
    ```
*   **Success Response (200 OK):**
    ```json
    {
      "success": true,
      "client_secret": "pi_3MtwJD2eZvKYlo2C0DGk4_secret_A1b2C3d4e5f6g7h8i9j0k1l2",
      "payment_intent": "pi_3MtwJD2eZvKYlo2C0DGk4",
      "amount": 50.00,
      "currency": "USD",
      "stripe_publishable_key": "pk_test_sample_stripe_publishable_key_12345",
      "wallet_topup": {
        "id": 3,
        "amount": 50.00,
        "stripe_payment_intent": "pi_3MtwJD2eZvKYlo2C0DGk4",
        "payment_status": "pending",
        "paid_at": null,
        "created_at": "2026-07-05T21:28:00+00:00"
      }
    }
    ```
*   **Business Logic Explanation:**
    *   Validates that the amount is positive (min $5.00, max $5000.00).
    *   Generates a Stripe PaymentIntent.
    *   Logs a pending `wallet_topups` record.
*   **Database Tables Affected:** `wallet_topups` (writes)

---

### 44. Request Wallet Withdrawal
*   **API Name:** Request Wallet Withdrawal
*   **Purpose:** Files a mock withdrawal request for the user's active wallet balance.
*   **Endpoint URL:** `/wallet/withdraw`
*   **HTTP Method:** `POST`
*   **Authentication Required:** Yes
*   **Headers:**
    *   `Accept: application/json`
    *   `Content-Type: application/json`
    *   `Authorization: Bearer {{auth_token}}`
*   **Request Payload:**
    ```json
    {
      "amount": 100.00,
      "bank_account_id": 2
    }
    ```
*   **Success Response (201 Created):**
    ```json
    {
      "success": true,
      "message": "Withdrawal request submitted successfully.",
      "withdrawal": {
        "id": 1,
        "amount": 100.00,
        "status": "pending",
        "bank_account_id": 2,
        "admin_note": null,
        "requested_at": "2026-07-05T21:32:00+00:00",
        "processed_at": null
      }
    }
    ```
*   **Business Logic Explanation:**
    *   Validates that the withdrawal amount is at least $10.00.
    *   Validates that the withdrawal amount does not exceed the current wallet balance.
    *   Withdrawal remains pending until approved or completed by an admin.
*   **Database Tables Affected:** `withdrawal_requests` (writes)

---

### 45. Get Withdrawals History
*   **API Name:** Get Withdrawals History
*   **Purpose:** Retrieves a paginated history of withdrawal requests.
*   **Endpoint URL:** `/wallet/withdrawals`
*   **HTTP Method:** `GET`
*   **Authentication Required:** Yes
*   **Headers:**
    *   `Accept: application/json`
    *   `Authorization: Bearer {{auth_token}}`
*   **Parameters (Query Parameters):**
    *   `page`: (Optional, Integer) Page number.
    *   `per_page`: (Optional, Integer) Items per page.
    *   `sort`: (Optional, String) Sorting method: `latest`, `oldest`.
*   **Success Response (200 OK):**
    ```json
    {
      "success": true,
      "withdrawals": [
        {
          "id": 1,
          "amount": 100.00,
          "status": "pending",
          "bank_account_id": 2,
          "admin_note": null,
          "requested_at": "2026-07-05T21:32:00+00:00",
          "processed_at": null
        }
      ],
      "meta": {
        "current_page": 1,
        "per_page": 15,
        "total": 1,
        "last_page": 1
      },
      "links": {
        "first": "https://api.domain.com/api/v1/wallet/withdrawals?page=1",
        "last": "https://api.domain.com/api/v1/wallet/withdrawals?page=1",
        "prev": null,
        "next": null
      }
    }
    ```
*   **Database Tables Affected:** `withdrawal_requests` (reads)

---

### 46. Get Withdrawal Details
*   **API Name:** Get Withdrawal Details
*   **Purpose:** Retrieves details of a specific withdrawal request.
*   **Endpoint URL:** `/wallet/withdrawals/{id}`
*   **HTTP Method:** `GET`
*   **Authentication Required:** Yes
*   **Headers:**
    *   `Accept: application/json`
    *   `Authorization: Bearer {{auth_token}}`
*   **Success Response (200 OK):**
    ```json
    {
      "success": true,
      "withdrawal": {
        "id": 1,
        "amount": 100.00,
        "status": "pending",
        "bank_account_id": 2,
        "admin_note": null,
        "requested_at": "2026-07-05T21:32:00+00:00",
        "processed_at": null
      }
    }
    ```
*   **Database Tables Affected:** `withdrawal_requests` (reads)

---

### 47. Stripe Webhook Listener
*   **API Name:** Stripe Webhook Listener
*   **Purpose:** Idempotently consumes Stripe PaymentIntent succeeded or failed event notifications to finalize wallet credits.
*   **Endpoint URL:** `/stripe/webhook`
*   **HTTP Method:** `POST`
*   **Authentication Required:** No (Signature verified via Header)
*   **Headers:**
    *   `Accept: application/json`
    *   `Stripe-Signature: t=1612345678,v1=sig_hash...`
*   **Success Response (200 OK):**
    ```json
    {
      "success": true
    }
    ```
*   **Business Logic Explanation:**
    *   Verifies webhook signature using configured `STRIPE_WEBHOOK_SECRET`.
    *   Validates event idempotency using `processed_stripe_events` to ignore duplicate events.
    *   If event is `payment_intent.succeeded`, updates the top-up status to completed, creates a wallet credit ledger entry, and increments the wallet balance.
*   **Database Tables Affected:** `processed_stripe_events` (writes), `wallet_topups` (updates), `wallets` (updates), `wallet_transactions` (writes)

---

## Module 6: Push Notifications & Device Token Management

### 48. Register Device
*   **API Name:** Register Device
*   **Purpose:** Registers or updates an FCM device token for push notification delivery.
*   **Endpoint URL:** `/devices/register`
*   **HTTP Method:** `POST`
*   **Authentication Required:** Yes
*   **Headers:**
    *   `Accept: application/json`
    *   `Content-Type: application/json`
    *   `Authorization: Bearer {{auth_token}}`
*   **Request Payload:**
    ```json
    {
      "device_type": "android",
      "device_name": "Pixel 7 Pro",
      "device_token": "fcm_token_123456",
      "platform": "Android",
      "os_version": "14.0",
      "app_version": "1.0.0",
      "language": "en",
      "timezone": "Europe/London"
    }
    ```
*   **Success Response (201 Created):**
    ```json
    {
      "success": true,
      "message": "Device registered successfully.",
      "device": {
        "id": 1,
        "device_type": "android",
        "device_name": "Pixel 7 Pro",
        "device_token": "fcm_token_123456",
        "platform": "Android",
        "os_version": "14.0",
        "app_version": "1.0.0",
        "language": "en",
        "timezone": "Europe/London"
      }
    }
    ```
*   **Database Tables Affected:** `user_devices` (writes)

---

### 49. Update Device
*   **API Name:** Update Device
*   **Purpose:** Updates the metadata or token of an existing registered device.
*   **Endpoint URL:** `/devices/{id}`
*   **HTTP Method:** `PUT`
*   **Authentication Required:** Yes
*   **Headers:**
    *   `Accept: application/json`
    *   `Content-Type: application/json`
    *   `Authorization: Bearer {{auth_token}}`
*   **Request Payload:**
    ```json
    {
      "device_name": "Pixel 7 Pro (Updated)",
      "app_version": "1.0.1"
    }
    ```
*   **Success Response (200 OK):**
    ```json
    {
      "success": true,
      "message": "Device updated successfully.",
      "device": {
        "id": 1,
        "device_type": "android",
        "device_name": "Pixel 7 Pro (Updated)",
        "device_token": "fcm_token_123456",
        "platform": "Android",
        "os_version": "14.0",
        "app_version": "1.0.1",
        "language": "en",
        "timezone": "Europe/London"
      }
    }
    ```
*   **Database Tables Affected:** `user_devices` (updates)

---

### 50. Delete Device
*   **API Name:** Delete Device
*   **Purpose:** Removes/deregisters a device token from receiving push notifications.
*   **Endpoint URL:** `/devices/{id}`
*   **HTTP Method:** `DELETE`
*   **Authentication Required:** Yes
*   **Headers:**
    *   `Accept: application/json`
    *   `Authorization: Bearer {{auth_token}}`
*   **Success Response (200 OK):**
    ```json
    {
      "success": true,
      "message": "Device deleted successfully."
    }
    ```
*   **Database Tables Affected:** `user_devices` (deletes)

---

### 51. Get Notifications
*   **API Name:** Get Notifications
*   **Purpose:** Retrieves a filtered, paginated list of notification logs for the authenticated user.
*   **Endpoint URL:** `/notifications`
*   **HTTP Method:** `GET`
*   **Authentication Required:** Yes
*   **Headers:**
    *   `Accept: application/json`
    *   `Authorization: Bearer {{auth_token}}`
*   **Query Parameters (Optional):**
    *   `page`: Page number (default: `1`)
    *   `per_page`: Number of logs per page (default: `15`)
    *   `sort`: Sort direction (`latest` or `oldest`, default: `latest`)
    *   `type`: Filter by notification type value
    *   `category`: Filter by category (e.g. `ride`, `wallet`, `payment`, `review`)
    *   `status`: Filter by status (e.g. `pending`, `sent`, `failed`, `read`)
    *   `search`: Keyword search on title and body
    *   `from_date`: Filter logs created on or after date (`YYYY-MM-DD`)
    *   `to_date`: Filter logs created on or before date (`YYYY-MM-DD`)
*   **Success Response (200 OK):**
    ```json
    {
      "success": true,
      "notifications": [
        {
          "id": 1,
          "title": "Ride Update",
          "body": "Your driver is arriving.",
          "type": "driver_arriving",
          "category": "ride",
          "priority": "high",
          "payload": {
            "ride_id": 12
          },
          "status": "sent",
          "firebase_message_id": "projects/uey/messages/0:1623...",
          "failure_reason": null,
          "sent_at": "2026-07-08T17:00:00Z",
          "read_at": null,
          "created_at": "2026-07-08T17:00:00Z"
        }
      ],
      "meta": {
        "current_page": 1,
        "per_page": 15,
        "total": 1,
        "last_page": 1
      }
    }
    ```
*   **Database Tables Affected:** `notification_logs` (reads)

---

### 52. Notification Details
*   **API Name:** Notification Details
*   **Purpose:** Retrieves details of a specific notification log.
*   **Endpoint URL:** `/notifications/{id}`
*   **HTTP Method:** `GET`
*   **Authentication Required:** Yes
*   **Headers:**
    *   `Accept: application/json`
    *   `Authorization: Bearer {{auth_token}}`
*   **Success Response (200 OK):**
    ```json
    {
      "success": true,
      "notification": {
        "id": 1,
        "title": "Ride Update",
        "body": "Your driver is arriving.",
        "type": "driver_arriving",
        "category": "ride",
        "priority": "high",
        "payload": {
          "ride_id": 12
        },
        "status": "sent",
        "firebase_message_id": "projects/uey/messages/0:1623...",
        "failure_reason": null,
        "sent_at": "2026-07-08T17:00:00Z",
        "read_at": null,
        "created_at": "2026-07-08T17:00:00Z"
      }
    }
    ```
*   **Database Tables Affected:** `notification_logs` (reads)

---

### 53. Mark Notification Read
*   **API Name:** Mark Notification Read
*   **Purpose:** Marks a specific notification log as read.
*   **Endpoint URL:** `/notifications/{id}/read`
*   **HTTP Method:** `POST`
*   **Authentication Required:** Yes
*   **Headers:**
    *   `Accept: application/json`
    *   `Authorization: Bearer {{auth_token}}`
*   **Success Response (200 OK):**
    ```json
    {
      "success": true,
      "message": "Notification marked as read."
    }
    ```
*   **Database Tables Affected:** `notification_logs` (updates)

---

### 54. Mark All Read
*   **API Name:** Mark All Read
*   **Purpose:** Marks all unread notification logs for the user as read.
*   **Endpoint URL:** `/notifications/read-all`
*   **HTTP Method:** `POST`
*   **Authentication Required:** Yes
*   **Headers:**
    *   `Accept: application/json`
    *   `Authorization: Bearer {{auth_token}}`
*   **Success Response (200 OK):**
    ```json
    {
      "success": true,
      "message": "All notifications marked as read."
    }
    ```
*   **Database Tables Affected:** `notification_logs` (updates)

---

### 55. Unread Count
*   **API Name:** Unread Count
*   **Purpose:** Gets the count of unread notification logs for the user.
*   **Endpoint URL:** `/notifications/unread-count`
*   **HTTP Method:** `GET`
*   **Authentication Required:** Yes
*   **Headers:**
    *   `Accept: application/json`
    *   `Authorization: Bearer {{auth_token}}`
*   **Success Response (200 OK):**
    ```json
    {
      "success": true,
      "unread_count": 0
    }
    ```
*   **Database Tables Affected:** `notification_logs` (reads)

---

### 56. Delete Notification
*   **API Name:** Delete Notification
*   **Purpose:** Soft-deletes a notification log.
*   **Endpoint URL:** `/notifications/{id}`
*   **HTTP Method:** `DELETE`
*   **Authentication Required:** Yes
*   **Headers:**
    *   `Accept: application/json`
    *   `Authorization: Bearer {{auth_token}}`
*   **Success Response (200 OK):**
    ```json
    {
      "success": true,
      "message": "Notification deleted successfully."
    }
    ```
*   **Database Tables Affected:** `notification_logs` (deletes/soft deletes)

---

### 57. Restore Notification
*   **API Name:** Restore Notification
*   **Purpose:** Restores a soft-deleted notification log.
*   **Endpoint URL:** `/notifications/{id}/restore`
*   **HTTP Method:** `POST`
*   **Authentication Required:** Yes
*   **Headers:**
    *   `Accept: application/json`
    *   `Authorization: Bearer {{auth_token}}`
*   **Success Response (200 OK):**
    ```json
    {
      "success": true,
      "message": "Notification restored successfully.",
      "notification": {
        "id": 1,
        "title": "Ride Update",
        "body": "Your driver is arriving."
      }
    }
    ```
*   **Database Tables Affected:** `notification_logs` (restores)

---

## Module 7: Admin Panel & Platform Operations

### 58. Admin Login
*   **API Name:** Admin Login
*   **Purpose:** Authenticates admin and returns access token.
*   **Endpoint URL:** `/admin/login`
*   **HTTP Method:** `POST`
*   **Authentication Required:** No
*   **Request Payload:**
    ```json
    {
      "phone": "+447999999999",
      "password": "password123"
    }
    ```
*   **Success Response (200 OK):**
    ```json
    {
      "success": true,
      "token": "1|abcdef...",
      "user": {
        "id": 1,
        "name": "Alice Admin",
        "email": "alice.admin@example.com",
        "phone": "+447999999999",
        "role": "admin"
      }
    }
    ```

### 59. Admin Logout
*   **API Name:** Admin Logout
*   **Purpose:** Revokes current session token.
*   **Endpoint URL:** `/admin/logout`
*   **HTTP Method:** `POST`
*   **Authentication Required:** Yes (Admin role)
*   **Success Response (200 OK):**
    ```json
    {
      "success": true,
      "message": "Logged out successfully."
    }
    ```

### 60. Get Dashboard Summary
*   **API Name:** Get Dashboard Summary
*   **Purpose:** Retrieves core metrics and chart distribution series.
*   **Endpoint URL:** `/admin/dashboard`
*   **HTTP Method:** `GET`
*   **Authentication Required:** Yes (Admin role)
*   **Success Response (200 OK):**
    ```json
    {
      "success": true,
      "data": {
        "metrics": {
          "total_riders": 50,
          "total_drivers": 20,
          "today_revenue": 150.00
        },
        "charts": {
          "daily_rides": []
        }
      }
    }
    ```

### 61. List Riders
*   **API Name:** List Riders
*   **Purpose:** Retrieves paginated, filterable lists of riders.
*   **Endpoint URL:** `/admin/riders`
*   **HTTP Method:** `GET`
*   **Authentication Required:** Yes (Admin role)
*   **Success Response (200 OK):**
    ```json
    {
      "success": true,
      "riders": []
    }
    ```

### 62. Approve Driver
*   **API Name:** Approve Driver
*   **Purpose:** Activates driver status and approves all pending documents.
*   **Endpoint URL:** `/admin/drivers/{id}/approve`
*   **HTTP Method:** `POST`
*   **Authentication Required:** Yes (Admin role)
*   **Success Response (200 OK):**
    ```json
    {
      "success": true,
      "message": "Driver approved successfully."
    }
    ```

### 63. Cancel Ride
*   **API Name:** Cancel Ride (Admin)
*   **Purpose:** Force cancels a pending/active ride with cancellation reason.
*   **Endpoint URL:** `/admin/rides/{id}/cancel`
*   **HTTP Method:** `POST`
*   **Authentication Required:** Yes (Admin role)
*   **Request Payload:**
    ```json
    {
      "cancel_reason": "Rider requested manual cancellation."
    }
    ```
*   **Success Response (200 OK):**
    ```json
    {
      "success": true,
      "message": "Ride cancelled successfully."
    }
    ```

### 64. Refund Ride
*   **API Name:** Refund Ride (Admin)
*   **Purpose:** Reverses ride fare transaction, crediting rider wallet and debiting driver wallet.
*   **Endpoint URL:** `/admin/rides/{id}/refund`
*   **HTTP Method:** `POST`
*   **Authentication Required:** Yes (Admin role)
*   **Success Response (200 OK):**
    ```json
    {
      "success": true,
      "message": "Ride payment refunded successfully."
    }
    ```

### 65. Credit Wallet (Admin)
*   **API Name:** Credit Wallet (Admin)
*   **Purpose:** Manually credits funds to user wallet with reason and audits.
*   **Endpoint URL:** `/admin/wallets/{id}/credit`
*   **HTTP Method:** `POST`
*   **Authentication Required:** Yes (Admin role)
*   **Request Payload:**
    ```json
    {
      "amount": 50.00,
      "reason": "Referral credit adjustment"
    }
    ```
*   **Success Response (200 OK):**
    ```json
    {
      "success": true,
      "message": "Wallet credited successfully."
    }
    ```

### 66. Broadcast Announcement
*   **API Name:** Broadcast Announcement
*   **Purpose:** Queues push and database broadcast announcements.
*   **Endpoint URL:** `/admin/notifications/broadcast`
*   **HTTP Method:** `POST`
*   **Authentication Required:** Yes (Admin role)
*   **Request Payload:**
    ```json
    {
      "target": "all_users",
      "title": "System Alert",
      "body": "System will undergo updates tonight.",
      "category": "system",
      "priority": "high",
      "channels": ["push", "database"]
    }
    ```
*   **Success Response (200 OK):**
    ```json
    {
      "success": true,
      "message": "Broadcast notification queued successfully."
    }
    ```

### 67. Save Settings
*   **API Name:** Save Settings
*   **Purpose:** Modifies system configurations.
*   **Endpoint URL:** `/admin/settings`
*   **HTTP Method:** `PUT`
*   **Authentication Required:** Yes (Admin role)
*   **Request Payload:**
    ```json
    {
      "platform_commission": 15.00,
      "currency": "USD"
    }
    ```
*   **Success Response (200 OK):**
    ```json
    {
      "success": true,
      "message": "System settings updated successfully."
    }
    ```

### 68. List Audit Logs
*   **API Name:** List Audit Logs
*   **Purpose:** Retrieves log history trails of all admin operations.
*   **Endpoint URL:** `/admin/audit-logs`
*   **HTTP Method:** `GET`
*   **Authentication Required:** Yes (Admin role)
*   **Success Response (200 OK):**
    ```json
    {
      "success": true,
      "audit_logs": []
    }
    ```

---

## Module 12: Laravel Reverb, Live Tracking & Real-Time Communication

This module defines the API endpoints, broadcast channels, and real-time events that power UEY's real-time ride tracking, messaging, and status updates.

### 69. Broadcast Channel Authentication
*   **API Name:** Broadcast Channel Authentication
*   **Purpose:** Authenticates private and presence channel subscriptions using Laravel Echo / Reverb.
*   **Endpoint URL:** `/broadcasting/auth` (Relative to `/api` root: `/api/broadcasting/auth`)
*   **HTTP Method:** `POST`
*   **Authentication Required:** Yes (Sanctum)
*   **Request Payload:**
    ```json
    {
      "channel_name": "private-rider.2",
      "socket_id": "1234.5678"
    }
    ```
*   **Success Response (200 OK):**
    ```json
    {
      "auth": "your-app-key:signature-hash",
      "channel_data": "optional-presence-user-info-string"
    }
    ```

### 70. Update Driver Live Location
*   **API Name:** Update Driver Location
*   **Purpose:** Updates the current location coordinates and metadata for online drivers. Logs history and broadcasts to the active ride channel.
*   **Endpoint URL:** `/driver/location`
*   **HTTP Method:** `POST`
*   **Authentication Required:** Yes (Driver role)
*   **Request Payload:**
    ```json
    {
      "latitude": 51.5080,
      "longitude": -0.1280,
      "heading": 120.0,
      "speed": 45.5,
      "accuracy": 5.0,
      "timestamp": 1700000000
    }
    ```
*   **Success Response (200 OK):**
    ```json
    {
      "success": true,
      "message": "Driver location updated successfully."
    }
    ```

### 71. Get Live Ride Tracking
*   **API Name:** Get Live Tracking
*   **Purpose:** Retrieves real-time coordinates, driver details, active vehicle details, heading, speed, and calculated ETA metrics for an active ride.
*   **Endpoint URL:** `/rides/{ride}/tracking`
*   **HTTP Method:** `GET`
*   **Authentication Required:** Yes (Rider involved in ride, or Admin)
*   **Success Response (200 OK):**
    ```json
    {
      "success": true,
      "tracking": {
        "driver": {
          "id": 2,
          "name": "Bob Driver"
        },
        "vehicle": {
          "make": "Toyota",
          "model": "Prius",
          "plate": "AB12 CDE"
        },
        "coordinates": {
          "latitude": 51.5080,
          "longitude": -0.1280
        },
        "heading": 120.0,
        "speed": 45.5,
        "eta": {
          "remaining_distance": 2.50,
          "remaining_time": 5,
          "estimated_arrival": "2026-07-10T18:51:30Z"
        },
        "status": "accepted",
        "last_updated": "2026-07-10T18:51:30Z"
      }
    }
    ```

### 72. Create/Get Chat Conversation
*   **API Name:** Create Conversation
*   **Purpose:** Establishes a new chat thread for an active ride or loads the existing one.
*   **Endpoint URL:** `/conversations`
*   **HTTP Method:** `POST`
*   **Authentication Required:** Yes (Rider/Driver involved in ride)
*   **Request Payload:**
    ```json
    {
      "ride_id": 1
    }
    ```
*   **Success Response (201 Created):**
    ```json
    {
      "success": true,
      "conversation": {
        "id": 1,
        "ride_id": 1,
        "driver_id": 2,
        "rider_id": 3,
        "created_at": "2026-07-10T18:51:30Z",
        "updated_at": "2026-07-10T18:51:30Z"
      }
    }
    ```

### 73. Send Message
*   **API Name:** Send Message
*   **Purpose:** Sends a message in a conversation. Supports text, image, or location coordinates.
*   **Endpoint URL:** `/messages`
*   **HTTP Method:** `POST`
*   **Authentication Required:** Yes (Involved parties)
*   **Request Payload:**
    ```json
    {
      "conversation_id": 1,
      "message": "Hello, I am waiting near the red building.",
      "type": "text"
    }
    ```
*   **Success Response (201 Created):**
    ```json
    {
      "success": true,
      "message": {
        "id": 10,
        "conversation_thread_id": 1,
        "sender_id": 3,
        "message": "Hello, I am waiting near the red building.",
        "type": "text",
        "status": "sent",
        "delivered_at": null,
        "read_at": null,
        "created_at": "2026-07-10T18:51:32Z"
      }
    }
    ```

### 74. Get Messages
*   **API Name:** Get Messages
*   **Purpose:** Retrieves message logs for a conversation thread.
*   **Endpoint URL:** `/messages`
*   **HTTP Method:** `GET`
*   **Authentication Required:** Yes (Involved parties)
*   **Query Parameters:**
    *   `conversation_id` (integer, optional)
    *   `ride_id` (integer, optional)
*   **Success Response (200 OK):**
    ```json
    {
      "success": true,
      "messages": [
        {
          "id": 10,
          "conversation_thread_id": 1,
          "sender_id": 3,
          "message": "Hello, I am waiting near the red building.",
          "type": "text",
          "status": "sent",
          "delivered_at": null,
          "read_at": null,
          "created_at": "2026-07-10T18:51:32Z"
        }
      ]
    }
    ```

### 75. Mark Message as Delivered
*   **API Name:** Mark Delivered
*   **Purpose:** Confirms message delivery, updates status, and broadcasts receipt event.
*   **Endpoint URL:** `/messages/{id}/delivered`
*   **HTTP Method:** `POST`
*   **Authentication Required:** Yes (Recipient user)
*   **Success Response (200 OK):**
    ```json
    {
      "success": true,
      "message": "Message marked as delivered.",
      "data": null
    }
    ```

### 76. Mark Message as Read
*   **API Name:** Mark Read
*   **Purpose:** Confirms message read status, updates status, and broadcasts receipt event.
*   **Endpoint URL:** `/messages/{id}/read`
*   **HTTP Method:** `POST`
*   **Authentication Required:** Yes (Recipient user)
*   **Success Response (200 OK):**
    ```json
    {
      "success": true,
      "message": "Message marked as read.",
      "data": null
    }
    ```

### 77. Typing Started / Stopped
*   **API Name:** Typing Start / Typing Stop
*   **Endpoints:**
    *   `/rides/{ride}/typing/start`
    *   `/rides/{ride}/typing/stop`
*   **HTTP Method:** `POST`
*   **Authentication Required:** Yes (Involved parties)
*   **Success Response (200 OK):**
    ```json
    {
      "success": true,
      "message": "Typing started broadcasted."
    }
    ```

---

## Module 13: Referral & Rewards System

This module defines the API endpoints for the referral system. Users can apply invitation codes, generate invitations for friends, view their current referral stats summary, and track their reward credits history.

### 78. Apply Referral Code
*   **API Name:** Apply Referral Code
*   **Purpose:** Applies a friend's referral code to the user's account before the first ride is completed.
*   **Endpoint URL:** `/referrals/apply`
*   **HTTP Method:** `POST`
*   **Authentication Required:** Yes
*   **Request Payload:**
    ```json
    {
      "referral_code": "UEY4K8PZ"
    }
    ```
*   **Success Response (200 OK):**
    ```json
    {
      "success": true,
      "message": "Referral code has been successfully applied to your account.",
      "referral": {
        "id": 1,
        "referrer_id": 1,
        "referred_user_id": 2,
        "referral_code": "UEY4K8PZ",
        "status": "pending",
        "first_ride_completed_at": null,
        "referrer_bonus": 10.0,
        "referred_bonus": 5.0,
        "rewarded_at": null,
        "created_at": "2026-07-11T12:00:00Z"
      }
    }
    ```
*   **Error Response (422 Unprocessable Content):**
    ```json
    {
      "success": false,
      "message": "You cannot refer yourself."
    }
    ```

### 79. Invite Friend
*   **API Name:** Invite Friend
*   **Purpose:** Generates referral sharing packages including referral code, message, and URL.
*   **Endpoint URL:** `/referrals/invite`
*   **HTTP Method:** `POST`
*   **Authentication Required:** Yes
*   **Request Payload:**
    ```json
    {
      "phone": "+447922222222"
    }
    ```
*   **Success Response (200 OK):**
    ```json
    {
      "success": true,
      "referral_code": "UEY4K8PZ",
      "invitation_message": "Use my referral code UEY4K8PZ to sign up and get a bonus on UEY Premium Mobility! Download here: https://uey.mobility/download?code=UEY4K8PZ",
      "share_url": "https://uey.mobility/download?code=UEY4K8PZ"
    }
    ```

### 80. Get My Referral Code
*   **API Name:** Get Referral Code
*   **Purpose:** Retrieves the authenticated user's unique referral code.
*   **Endpoint URL:** `/referrals/code`
*   **HTTP Method:** `GET`
*   **Authentication Required:** Yes
*   **Success Response (200 OK):**
    ```json
    {
      "success": true,
      "referral_code": "UEY4K8PZ"
    }
    ```

### 81. Referral History List
*   **API Name:** Referral History
*   **Purpose:** Retrieves a list of users referred by the authenticated user.
*   **Endpoint URL:** `/referrals/history`
*   **HTTP Method:** `GET`
*   **Authentication Required:** Yes
*   **Success Response (200 OK):**
    ```json
    {
      "success": true,
      "referrals": [
        {
          "id": 1,
          "referred_user": {
            "id": 2,
            "name": "Bob Friend",
            "phone": "+447922222222",
            "email": "bob@example.com"
          },
          "status": "pending",
          "first_ride_completed": false,
          "first_ride_completed_at": null,
          "created_at": "2026-07-11T12:00:00Z"
        }
      ]
    }
    ```

### 82. Referral Summary Stats
*   **API Name:** Referral Summary
*   **Purpose:** Retrieves totals, pending counts, and total completed statistics.
*   **Endpoint URL:** `/referrals/summary`
*   **HTTP Method:** `GET`
*   **Authentication Required:** Yes
*   **Success Response (200 OK):**
    ```json
    {
      "success": true,
      "total_referred": 1,
      "completed_referrals": 0,
      "pending_referrals": 1,
      "total_earnings": 0.0
    }
    ```

### 83. Referral Earnings History Ledger
*   **API Name:** Referral Earnings
*   **Purpose:** Retrieves the transaction history of credits earned from referrals.
*   **Endpoint URL:** `/referrals/earnings`
*   **HTTP Method:** `GET`
*   **Authentication Required:** Yes
*   **Success Response (200 OK):**
    ```json
    {
      "success": true,
      "earnings": [
        {
          "id": 15,
          "amount": 10.0,
          "type": "credit",
          "transaction_type": "referral_bonus",
          "status": "completed",
          "reference": "ref_bonus_referrer_1",
          "remarks": "Referral bonus received",
          "created_at": "2026-07-11T12:00:00Z"
        }
      ]
    }
    ```

---

## Real-Time Channel & Event Specifications

### WebSocket Channels
*   **Private Channels:**
    *   `rider.{userId}`: Emits rider-specific updates.
    *   `driver.{userId}`: Emits driver-specific updates.
    *   `wallet.{walletId}`: Emits wallet balance updates.
    *   `notification.{userId}`: Emits real-time in-app notification logs.
    *   `ride.{rideId}`: Shared channel for active ride telemetry, coordinates, typing indicators, and message logs.
*   **Presence Channels:**
    *   `drivers`: Online drivers availability and locations index.
    *   `admins`: Real-time analytics feeds and operations dashboard logs.

### Broadcast Events
1.  `DriverLocationUpdated`: Broadcasts live coordinates on `ride.{rideId}`.
2.  `RideRequested`: Emits new ride booking details on `presence-admins`.
3.  `RideAccepted`: Broadcasts acceptance details on `rider.{riderId}` and `presence-admins`.
4.  `DriverArriving` / `DriverArrived`: Emits status events on `rider.{riderId}`.
5.  `RideStarted` / `RideCompleted`: Broadcasts ride execution updates.
6.  `RideCancelled`: Emits cancel reasons on rider, driver, and admin channels.
7.  `WalletUpdated` / `PaymentCompleted`: Emits wallet balance modifications.
8.  `ReviewSubmitted`: Broadcasts ratings on admin presence feed.
9.  `MessageSent` / `MessageDelivered` / `MessageRead`: Emits chat states on `ride.{rideId}`.
11. `DriverStatusChanged`: Emits driver online, offline, busy states on `presence-drivers` and `presence-admins`.


---

## Phase 14: Favorite Places & Emergency SOS

### 1. Favorite Places

#### GET /favorite-places
*   **Purpose:** List all favorite places saved by the authenticated rider.
*   **Authentication Required:** Yes
*   **Success Response (200 OK):**
    ```json
    {
      "success": true,
      "data": [
        {
          "id": 1,
          "user_id": 1,
          "type": "home",
          "label": "Home",
          "nickname": "Sweet Home",
          "google_place_id": "place_id_123",
          "address": "221B Baker St",
          "latitude": 51.5237,
          "longitude": -0.1585,
          "is_default": true,
          "created_at": "2026-07-11T12:00:00Z"
        }
      ]
    }
    ```

#### GET /favorite-places/default
*   **Purpose:** Get defaults (Home, Work, and nearest Saved Place based on query coordinates).
*   **Authentication Required:** Yes
*   **Query Parameters:**
    *   `latitude`: decimal (optional)
    *   `longitude`: decimal (optional)
*   **Success Response (200 OK):**
    ```json
    {
      "success": true,
      "home": {
        "id": 1,
        "user_id": 1,
        "type": "home",
        "label": "Home",
        "nickname": "Sweet Home",
        "address": "221B Baker St",
        "latitude": 51.5237,
        "longitude": -0.1585,
        "is_default": true
      },
      "work": {
        "id": 2,
        "user_id": 1,
        "type": "work",
        "label": "Office",
        "nickname": "UEY HQ",
        "address": "1 Canada Square",
        "latitude": 51.5033,
        "longitude": -0.0195,
        "is_default": false
      },
      "nearest_saved_place": {
        "id": 3,
        "user_id": 1,
        "type": "saved",
        "label": "Gym",
        "nickname": "Iron Temple",
        "address": "10 Park Avenue",
        "latitude": 51.5200,
        "longitude": -0.1500,
        "is_default": false
      }
    }
    ```

#### POST /favorite-places
*   **Purpose:** Create a new favorite place. Prevents 20m coordinate collision and single Home/Work constraint.
*   **Authentication Required:** Yes
*   **Request Payload:**
    ```json
    {
      "type": "home",
      "label": "My Home",
      "address": "221B Baker St",
      "latitude": 51.5237,
      "longitude": -0.1585
    }
    ```
*   **Success Response (201 Created):**
    ```json
    {
      "success": true,
      "data": {
        "id": 1,
        "user_id": 1,
        "type": "home",
        "label": "My Home",
        "nickname": null,
        "google_place_id": null,
        "address": "221B Baker St",
        "latitude": 51.5237,
        "longitude": -0.1585,
        "is_default": true,
        "created_at": "2026-07-11T12:00:00Z"
      }
    }
    ```
*   **Error Responses:**
    *   `422 Unprocessable Content`: If coordinates are within 20m of another place or if type Home/Work is duplicated.
    *   `401 Unauthorized`: Missing bearer token.

#### PUT /favorite-places/{id}
*   **Purpose:** Update an existing favorite place.
*   **Success Response (200 OK):**
    ```json
    {
      "success": true,
      "data": {
        "id": 1,
        "user_id": 1,
        "type": "home",
        "label": "Home (Updated)",
        "nickname": "Old Home",
        "address": "221B Baker St",
        "latitude": 51.5237,
        "longitude": -0.1585,
        "is_default": true,
        "created_at": "2026-07-11T12:00:00Z"
      }
    }
    ```

#### DELETE /favorite-places/{id}
*   **Purpose:** Delete a favorite place.
*   **Success Response (200 OK):**
    ```json
    {
      "success": true,
      "message": "Favorite place deleted successfully."
    }
    ```

---

### 2. Emergency Contacts

#### GET /emergency-contacts
*   **Purpose:** List all emergency contacts.
*   **Success Response (200 OK):**
    ```json
    {
      "success": true,
      "data": [
        {
          "id": 1,
          "user_id": 1,
          "name": "John Doe",
          "phone": "+447911111111",
          "relationship": "Brother",
          "priority": 1,
          "created_at": "2026-07-11T12:00:00Z"
        }
      ]
    }
    ```

#### GET /emergency-contacts/default
*   **Purpose:** Get emergency contacts ordered by priority ascending.
*   **Success Response (200 OK):**
    ```json
    {
      "success": true,
      "contacts": [
        {
          "id": 1,
          "name": "John Doe",
          "phone": "+447911111111",
          "relationship": "Brother",
          "priority": 1
        }
      ]
    }
    ```

#### POST /emergency-contacts
*   **Purpose:** Save a new emergency contact. Restricts limits using settings.
*   **Success Response (201 Created):**
    ```json
    {
      "success": true,
      "data": {
        "id": 1,
        "user_id": 1,
        "name": "John Doe",
        "phone": "+447911111111",
        "relationship": "Brother",
        "priority": 1,
        "created_at": "2026-07-11T12:00:00Z"
      }
    }
    ```

---

### 3. Emergency SOS Alerts

#### POST /rides/{ride}/sos
*   **Purpose:** Trigger an active SOS alert on a ride. Checks conflict (409) if an active SOS already exists.
*   **Request Payload (multipart/form-data):**
    *   `latitude`: decimal
    *   `longitude`: decimal
    *   `message`: string (optional)
    *   `photo`: file (optional)
*   **Success Response (201 Created):**
    ```json
    {
      "success": true,
      "data": {
        "id": 1,
        "ride_id": 1,
        "user_id": 1,
        "driver_id": 2,
        "status": "active",
        "latitude": 51.5237,
        "longitude": -0.1585,
        "message": "Help me!",
        "attachment": "sos/evidence.jpg",
        "attachment_type": "photo",
        "created_at": "2026-07-11T12:00:00Z"
      }
    }
    ```
*   **Error Responses:**
    *   `409 Conflict`: If another active SOS alert is already in progress for the ride.
    *   `422 Unprocessable Content`: If the ride is not active.

#### POST /emergency-alerts/{id}/acknowledge
*   **Purpose:** Driver acknowledges an active SOS alert.
*   **Success Response (200 OK):**
    ```json
    {
      "success": true,
      "message": "SOS Alert acknowledged by driver."
    }
    ```

#### POST /emergency-alerts/{id}/resolve
*   **Purpose:** Rider resolves their own SOS alert.
*   **Success Response (200 OK):**
    ```json
    {
      "success": true,
      "message": "SOS Alert resolved successfully."
    }
    ```

#### GET /emergency-alerts
*   **Purpose:** List SOS alerts associated with the user.
*   **Success Response (200 OK):**
    ```json
    {
      "success": true,
      "data": [
        {
          "id": 1,
          "ride_id": 1,
          "user_id": 1,
          "driver_id": 2,
          "status": "active",
          "latitude": 51.5237,
          "longitude": -0.1585,
          "message": "Help me!"
        }
      ]
    }
    ```

#### GET /emergency-alerts/{id}
*   **Purpose:** View details of a specific SOS alert including its timeline history.
*   **Success Response (200 OK):**
    ```json
    {
      "success": true,
      "data": {
        "id": 1,
        "ride_id": 1,
        "user_id": 1,
        "driver_id": 2,
        "status": "active",
        "latitude": 51.5237,
        "longitude": -0.1585,
        "message": "Help me!",
        "histories": [
          {
            "id": 1,
            "status": "active",
            "message": "Emergency SOS triggered by rider.",
            "created_at": "2026-07-11T12:00:00Z"
          }
        ]
      }
    }
    ```

---

### 4. Admin SOS Moderation

#### GET /admin/emergency-alerts
*   **Purpose:** Retrieve list of all SOS alerts across the platform (Admin Only).
*   **Success Response (200 OK):**
    ```json
    {
      "success": true,
      "data": [
        {
          "id": 1,
          "ride_id": 1,
          "user_id": 1,
          "driver_id": 2,
          "status": "active",
          "latitude": 51.5237,
          "longitude": -0.1585
        }
      ]
    }
    ```

#### GET /admin/emergency-alerts/statistics
*   **Purpose:** Retrieve consolidated SOS statistics (Admin Only).
*   **Success Response (200 OK):**
    ```json
    {
      "success": true,
      "data": {
        "today": 5,
        "weekly": 20,
        "monthly": 60,
        "active": 2,
        "resolved": 15,
        "average_response_time": 320.50
      }
    }
    ```

#### POST /admin/emergency-alerts/{id}/assign
*   **Purpose:** Assigns the logged-in administrator to manage the SOS alert (Admin Only).
*   **Success Response (200 OK):**
    ```json
    {
      "success": true,
      "message": "SOS Alert assigned successfully."
    }
    ```

#### POST /admin/emergency-alerts/{id}/resolve
*   **Purpose:** Resolves the SOS alert and logs notes (Admin Only).
*   **Request Payload:**
    ```json
    {
      "admin_note": "Resolved by contacting driver."
    }
    ```
*   **Success Response (200 OK):**
    ```json
    {
      "success": true,
      "message": "SOS Alert resolved successfully by administrator."
    }
    ```

#### POST /admin/emergency-alerts/{id}/close
*   **Purpose:** Closes resolved alert (Admin Only).
*   **Success Response (200 OK):**
    ```json
    {
      "success": true,
      "message": "SOS Alert closed successfully."
    }
    ```


---

## 15. Wallet Ledger (Immutable Audit Journal)

The Ledger is a **read-only, append-only** immutable accounting journal that mirrors every `wallet_transaction` for compliance, audit, and finance reporting. It does **NOT** replace `wallet_transactions` — those remain the single source of truth for balances.

> **Architecture Rule:** Every `WalletService::credit()` and `WalletService::debit()` call automatically writes a matching ledger entry. No manual API call is needed to create ledger entries.

---

### Data Model

| Field | Type | Description |
|---|---|---|
| `id` | integer | Ledger entry ID |
| `wallet_transaction_id` | integer | 1:1 unique link to `wallet_transactions` |
| `wallet_id` | integer | Parent wallet |
| `user_id` | integer | Owner of the wallet |
| `reference` | string | Payment reference (nullable) |
| `transaction_type` | string | e.g. `ride_payment`, `top_up`, `referral_bonus` |
| `direction` | string | `credit` or `debit` |
| `amount` | decimal | Transaction amount |
| `currency` | string | ISO currency code (e.g. `GBP`) |
| `source` | string | Origin source — see `LedgerSource` enum below |
| `remarks` | string | Optional human-readable notes (nullable) |
| `metadata` | json | Arbitrary context: payment gateway, promo code, etc. |
| `created_at` | datetime | Immutable creation timestamp |

> **Immutability Guarantees:**
> - Ledger rows can **never** be updated or deleted.
> - The model throws `RuntimeException: Ledger entries are immutable` on any update or delete attempt.
> - `wallet_transaction_id` has a unique database index — only one ledger entry per transaction.

---

### LedgerSource Enum Values

| Value | Description |
|---|---|
| `ride_payment` | Deducted for a completed ride |
| `wallet_topup` | Wallet funded by rider (Stripe / Cash) |
| `withdrawal` | Driver payout / withdrawal |
| `refund` | Ride cancellation refund |
| `referral_bonus` | Referrer or invitee bonus |
| `admin_credit` | Manual credit applied by admin |
| `admin_debit` | Manual debit applied by admin |
| `promo_credit` | Promotional discount credit |
| `manual_adjustment` | Other manual adjustment |
| `stripe` | Stripe gateway top-up override |
| `cash` | Cash payment override |

---

### Rider Endpoints

#### GET /api/v1/wallet/ledger

Returns the authenticated rider's own immutable ledger history. Read-only.

- **Auth:** Bearer Token (`role:rider` or `role:driver`)
- **Query Parameters (optional):**

| Param | Type | Example |
|---|---|---|
| `date_from` | date | `2026-07-01` |
| `date_to` | date | `2026-07-31` |
| `direction` | string | `credit` or `debit` |
| `per_page` | integer | `20` (default) |

- **Success Response (200 OK):**

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "wallet_transaction_id": 10,
      "wallet_id": 2,
      "user_id": 3,
      "reference": "RIDE_PAY_001",
      "transaction_type": "ride_payment",
      "direction": "debit",
      "amount": 12.50,
      "currency": "GBP",
      "source": "ride_payment",
      "remarks": null,
      "metadata": {},
      "created_at": "2026-07-11T12:00:00+00:00"
    }
  ],
  "meta": {
    "total": 1,
    "per_page": 20,
    "current_page": 1,
    "last_page": 1
  }
}
```

---

### Admin Endpoints

#### GET /api/v1/admin/ledgers

Returns all ledger entries with optional filtering. Admin only.

- **Auth:** Bearer Token (`role:admin`)
- **Query Parameters (optional):**

| Param | Type | Description |
|---|---|---|
| `date_from` | date | Start date filter (YYYY-MM-DD) |
| `date_to` | date | End date filter (YYYY-MM-DD) |
| `wallet_id` | integer | Filter by wallet |
| `user_id` | integer | Filter by user |
| `transaction_type` | string | e.g. `ride_payment`, `top_up` |
| `source` | string | e.g. `stripe`, `referral_bonus` |
| `reference` | string | Partial match on reference field |
| `direction` | string | `credit` or `debit` |
| `per_page` | integer | Results per page (default 20) |

- **Success Response (200 OK):**

```json
{
  "success": true,
  "data": [
    {
      "id": 5,
      "wallet_transaction_id": 22,
      "user_id": 7,
      "direction": "credit",
      "amount": 10.00,
      "currency": "GBP",
      "source": "referral_bonus",
      "transaction_type": "referral_bonus",
      "created_at": "2026-07-10T09:30:00+00:00"
    }
  ],
  "meta": {
    "total": 200,
    "per_page": 20,
    "current_page": 1,
    "last_page": 10
  }
}
```

---

#### GET /api/v1/admin/ledgers/{id}

Returns a single ledger entry with full linked details (wallet, user, wallet transaction). Admin only.

- **Auth:** Bearer Token (`role:admin`)
- **Path Parameter:** `id` — Ledger entry ID

- **Success Response (200 OK):**

```json
{
  "success": true,
  "data": {
    "id": 1,
    "wallet_transaction_id": 10,
    "wallet_id": 2,
    "user_id": 3,
    "reference": "RIDE_PAY_001",
    "transaction_type": "ride_payment",
    "direction": "debit",
    "amount": 12.50,
    "currency": "GBP",
    "source": "ride_payment",
    "remarks": null,
    "metadata": {},
    "created_at": "2026-07-11T12:00:00+00:00",
    "wallet_transaction": {
      "id": 10,
      "type": "debit",
      "status": "completed",
      "balance_before": 100.00,
      "balance_after": 87.50,
      "payment_gateway": "stripe",
      "created_at": "2026-07-11T12:00:00+00:00"
    },
    "user": {
      "id": 3,
      "name": "John Rider",
      "email": "john@example.com",
      "phone": "+447911000001",
      "role": "rider"
    },
    "wallet": {
      "id": 2,
      "balance": 87.50,
      "currency": "GBP",
      "status": "active"
    }
  }
}
```

- **Error Response (404 Not Found):**

```json
{
  "success": false,
  "message": "Ledger entry not found."
}
```

---

### Artisan Commands

| Command | Description |
|---|---|
| `php artisan app:ledger-backfill` | Backfill missing ledger entries for all historical wallet transactions. **Fully idempotent** — safe to run multiple times. |
| `php artisan app:wallet-settlement` | Daily audit: detects balance inconsistencies **and** auto-creates any missing ledger rows. |

**Backfill output example:**
```
+-------------------------------+-------+
| Metric                        | Count |
+-------------------------------+-------+
| Total transactions scanned    | 450   |
| Ledger entries created        | 380   |
| Already existed (skipped)     | 70    |
+-------------------------------+-------+
```
