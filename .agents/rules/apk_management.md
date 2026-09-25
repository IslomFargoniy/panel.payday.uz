# Android APK Management Rules

## `public/downloads/` Directory Constraints

1. **Only ONE APK file permitted**:
   - The directory `public/downloads/` must NEVER contain multiple `.apk` files.
   - Do NOT create symlinks like `payday.apk` alongside `payday-vX.Y.Z.apk`.
   - Do NOT leave `app-debug.apk` or older versions in the folder.
   - The folder must contain strictly:
     - `payday-v{version}.apk` (the single latest build)
     - `version.json`

2. **Naming convention**:
   - Must strictly follow `payday-v{version}.apk` (e.g., `payday-v1.0.0.apk`).

3. **Cleanup before placement**:
   - Before copying or building a new APK into `public/downloads/`, delete all existing `*.apk` files in that folder.
   - Sync this strictly to the production server so only the single latest APK exists there as well.
