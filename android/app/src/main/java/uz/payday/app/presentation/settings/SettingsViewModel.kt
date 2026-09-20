package uz.payday.app.presentation.settings

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import uz.payday.app.domain.model.Resource
import uz.payday.app.domain.model.User
import uz.payday.app.domain.repository.AuthRepository
import javax.inject.Inject

data class SettingsUiState(
    val user: User? = null,
    val language: String = "uz",
    val themeMode: String = "system",
    val isBiometricEnabled: Boolean = false,
    val isLoggedOut: Boolean = false,
    val isLoading: Boolean = false,
    val errorMessage: String? = null,
    val successMessage: String? = null
)

@HiltViewModel
class SettingsViewModel @Inject constructor(
    private val authRepository: AuthRepository
) : ViewModel() {

    private val _uiState = MutableStateFlow(SettingsUiState())
    val uiState: StateFlow<SettingsUiState> = _uiState.asStateFlow()

    init {
        observeSettings()
        loadUserProfile()
    }

    private fun observeSettings() {
        viewModelScope.launch {
            authRepository.getLanguage().collect { lang ->
                _uiState.value = _uiState.value.copy(language = lang)
            }
        }
        viewModelScope.launch {
            authRepository.getThemeMode().collect { mode ->
                _uiState.value = _uiState.value.copy(themeMode = mode)
            }
        }
        viewModelScope.launch {
            authRepository.isBiometricEnabled().collect { bio ->
                _uiState.value = _uiState.value.copy(isBiometricEnabled = bio)
            }
        }
    }

    fun loadUserProfile() {
        viewModelScope.launch {
            when (val res = authRepository.getMe()) {
                is Resource.Success -> {
                    _uiState.value = _uiState.value.copy(user = res.data)
                }
                else -> Unit
            }
        }
    }

    fun setLanguage(lang: String) {
        viewModelScope.launch {
            authRepository.setLanguage(lang)
        }
    }

    fun setThemeMode(mode: String) {
        viewModelScope.launch {
            authRepository.setThemeMode(mode)
        }
    }

    fun setBiometricEnabled(enabled: Boolean) {
        viewModelScope.launch {
            authRepository.setBiometricEnabled(enabled)
        }
    }

    fun logout() {
        viewModelScope.launch {
            authRepository.logout()
            _uiState.value = _uiState.value.copy(isLoggedOut = true)
        }
    }

    fun updateProfile(name: String, email: String, phone: String?) {
        viewModelScope.launch {
            _uiState.value = _uiState.value.copy(isLoading = true, errorMessage = null)
            when (val res = authRepository.updateProfile(name, email, phone)) {
                is Resource.Success -> {
                    _uiState.value = _uiState.value.copy(
                        user = res.data,
                        isLoading = false,
                        successMessage = "Profil muvaffaqiyatli yangilandi"
                    )
                }
                is Resource.Error -> {
                    _uiState.value = _uiState.value.copy(
                        isLoading = false,
                        errorMessage = res.message ?: "Profilni yangilashda xatolik"
                    )
                }
                is Resource.Loading -> Unit
            }
        }
    }

    fun changePassword(current: String, newPass: String) {
        viewModelScope.launch {
            _uiState.value = _uiState.value.copy(isLoading = true, errorMessage = null)
            when (val res = authRepository.changePassword(current, newPass)) {
                is Resource.Success -> {
                    _uiState.value = _uiState.value.copy(
                        isLoading = false,
                        successMessage = "Parol muvaffaqiyatli o'zgartirildi"
                    )
                }
                is Resource.Error -> {
                    _uiState.value = _uiState.value.copy(
                        isLoading = false,
                        errorMessage = res.message ?: "Parolni o'zgartirishda xatolik"
                    )
                }
                is Resource.Loading -> Unit
            }
        }
    }
}
