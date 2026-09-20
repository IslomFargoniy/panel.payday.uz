package uz.payday.app.presentation.auth

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.firstOrNull
import kotlinx.coroutines.launch
import uz.payday.app.domain.model.Resource
import uz.payday.app.domain.model.User
import uz.payday.app.domain.repository.AuthRepository
import javax.inject.Inject

data class LoginUiState(
    val email: String = "admin@payday.uz",
    val password: String = "password",
    val baseUrl: String = "http://10.0.2.2:8000/api/",
    val isLoading: Boolean = false,
    val errorMessage: String? = null,
    val isSuccess: Boolean = false,
    val showServerConfig: Boolean = false
)

@HiltViewModel
class LoginViewModel @Inject constructor(
    private val authRepository: AuthRepository
) : ViewModel() {

    private val _uiState = MutableStateFlow(LoginUiState())
    val uiState: StateFlow<LoginUiState> = _uiState.asStateFlow()

    init {
        viewModelScope.launch {
            val savedBaseUrl = authRepository.getBaseUrl().firstOrNull()
            if (!savedBaseUrl.isNullOrBlank()) {
                _uiState.value = _uiState.value.copy(baseUrl = savedBaseUrl)
            }
        }
    }

    fun onEmailChanged(email: String) {
        _uiState.value = _uiState.value.copy(email = email, errorMessage = null)
    }

    fun onPasswordChanged(password: String) {
        _uiState.value = _uiState.value.copy(password = password, errorMessage = null)
    }

    fun onBaseUrlChanged(url: String) {
        _uiState.value = _uiState.value.copy(baseUrl = url)
    }

    fun toggleServerConfig() {
        _uiState.value = _uiState.value.copy(showServerConfig = !_uiState.value.showServerConfig)
    }

    fun login() {
        val state = _uiState.value
        if (state.email.isBlank() || state.password.isBlank()) {
            _uiState.value = state.copy(errorMessage = "Email va parolni kiriting")
            return
        }

        viewModelScope.launch {
            _uiState.value = state.copy(isLoading = true, errorMessage = null)
            authRepository.setBaseUrl(state.baseUrl)

            when (val result = authRepository.login(state.email.trim(), state.password)) {
                is Resource.Success -> {
                    _uiState.value = _uiState.value.copy(isLoading = false, isSuccess = true)
                }
                is Resource.Error -> {
                    _uiState.value = _uiState.value.copy(
                        isLoading = false,
                        errorMessage = result.message ?: "Kirishda xatolik yuz berdi"
                    )
                }
                is Resource.Loading -> Unit
            }
        }
    }
}
