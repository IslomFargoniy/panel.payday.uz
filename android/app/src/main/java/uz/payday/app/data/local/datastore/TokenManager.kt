package uz.payday.app.data.local.datastore

import android.content.Context
import androidx.datastore.core.DataStore
import androidx.datastore.preferences.core.Preferences
import androidx.datastore.preferences.core.booleanPreferencesKey
import androidx.datastore.preferences.core.edit
import androidx.datastore.preferences.core.intPreferencesKey
import androidx.datastore.preferences.core.stringPreferencesKey
import androidx.datastore.preferences.preferencesDataStore
import dagger.hilt.android.qualifiers.ApplicationContext
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.map
import javax.inject.Inject
import javax.inject.Singleton

private val Context.dataStore: DataStore<Preferences> by preferencesDataStore(name = "payday_prefs")

@Singleton
class TokenManager @Inject constructor(
    @ApplicationContext private val context: Context
) {
    companion object {
        private val KEY_TOKEN = stringPreferencesKey("auth_token")
        private val KEY_USER_ID = intPreferencesKey("user_id")
        private val KEY_USER_NAME = stringPreferencesKey("user_name")
        private val KEY_USER_EMAIL = stringPreferencesKey("user_email")
        private val KEY_USER_PHONE = stringPreferencesKey("user_phone")
        private val KEY_USER_ROLE = stringPreferencesKey("user_role")
        private val KEY_LANGUAGE = stringPreferencesKey("app_language")
        private val KEY_DARK_THEME = stringPreferencesKey("app_theme_mode") // "system", "light", "dark"
        private val KEY_BIOMETRIC_ENABLED = booleanPreferencesKey("biometric_enabled")
        private val KEY_PIN_CODE = stringPreferencesKey("pin_code")
        private val KEY_BASE_URL = stringPreferencesKey("base_url")
        
        const val DEFAULT_BASE_URL = "http://10.0.2.2:8000/api/"
    }

    val tokenFlow: Flow<String?> = context.dataStore.data.map { it[KEY_TOKEN] }
    val userIdFlow: Flow<Int?> = context.dataStore.data.map { it[KEY_USER_ID] }
    val userNameFlow: Flow<String?> = context.dataStore.data.map { it[KEY_USER_NAME] }
    val userEmailFlow: Flow<String?> = context.dataStore.data.map { it[KEY_USER_EMAIL] }
    val languageFlow: Flow<String> = context.dataStore.data.map { it[KEY_LANGUAGE] ?: "uz" }
    val themeModeFlow: Flow<String> = context.dataStore.data.map { it[KEY_DARK_THEME] ?: "system" }
    val biometricEnabledFlow: Flow<Boolean> = context.dataStore.data.map { it[KEY_BIOMETRIC_ENABLED] ?: false }
    val pinCodeFlow: Flow<String?> = context.dataStore.data.map { it[KEY_PIN_CODE] }
    val baseUrlFlow: Flow<String> = context.dataStore.data.map { it[KEY_BASE_URL] ?: DEFAULT_BASE_URL }

    suspend fun saveAuthSession(
        token: String,
        id: Int,
        name: String,
        email: String,
        phone: String? = null,
        roles: List<String> = emptyList()
    ) {
        context.dataStore.edit { prefs ->
            prefs[KEY_TOKEN] = token
            prefs[KEY_USER_ID] = id
            prefs[KEY_USER_NAME] = name
            prefs[KEY_USER_EMAIL] = email
            if (phone != null) prefs[KEY_USER_PHONE] = phone
            if (roles.isNotEmpty()) prefs[KEY_USER_ROLE] = roles.first()
        }
    }

    suspend fun clearSession() {
        context.dataStore.edit { prefs ->
            prefs.remove(KEY_TOKEN)
            prefs.remove(KEY_USER_ID)
            prefs.remove(KEY_USER_NAME)
            prefs.remove(KEY_USER_EMAIL)
            prefs.remove(KEY_USER_PHONE)
            prefs.remove(KEY_USER_ROLE)
        }
    }

    suspend fun setLanguage(language: String) {
        context.dataStore.edit { it[KEY_LANGUAGE] = language }
    }

    suspend fun setThemeMode(mode: String) {
        context.dataStore.edit { it[KEY_DARK_THEME] = mode }
    }

    suspend fun setBiometricEnabled(enabled: Boolean) {
        context.dataStore.edit { it[KEY_BIOMETRIC_ENABLED] = enabled }
    }

    suspend fun setPinCode(pin: String?) {
        context.dataStore.edit {
            if (pin != null) it[KEY_PIN_CODE] = pin else it.remove(KEY_PIN_CODE)
        }
    }

    suspend fun setBaseUrl(url: String) {
        val sanitized = if (!url.endsWith("/")) "$url/" else url
        val finalUrl = if (!sanitized.endsWith("/api/")) "${sanitized}api/" else sanitized
        context.dataStore.edit { it[KEY_BASE_URL] = finalUrl }
    }
}
