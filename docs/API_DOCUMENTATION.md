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
      }
    }
    ```
*   **Business Logic Explanation:**
    *   Validates input values (distance >= 0, duration >= 0).
    *   Calculates final fare: `base_fare + per_km_rate * actual_distance + per_minute_rate * actual_duration`, capped at the category's `minimum_fare`.
    *   Saves the detailed invoice items under `fare_breakdown` JSON column.
    *   Updates the driver's location coordinate fields and coordinates inside Redis to the destination of the ride to mark availability nearby.
*   **Database Tables Affected:** `rides`, `driver_profiles`, `ride_status_logs`



