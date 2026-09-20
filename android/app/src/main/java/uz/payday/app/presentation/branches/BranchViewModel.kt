package uz.payday.app.presentation.branches

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import uz.payday.app.domain.model.Branch
import uz.payday.app.domain.model.Firm
import uz.payday.app.domain.model.Resource
import uz.payday.app.domain.repository.FirmRepository
import javax.inject.Inject

data class BranchUiState(
    val firms: List<Firm> = emptyList(),
    val branches: List<Branch> = emptyList(),
    val devices: List<Map<String, Any>> = emptyList(),
    val isLoading: Boolean = false,
    val errorMessage: String? = null
)

@HiltViewModel
class BranchViewModel @Inject constructor(
    private val firmRepository: FirmRepository
) : ViewModel() {

    private val _uiState = MutableStateFlow(BranchUiState())
    val uiState: StateFlow<BranchUiState> = _uiState.asStateFlow()

    init {
        loadData()
    }

    fun loadData() {
        viewModelScope.launch {
            _uiState.value = _uiState.value.copy(isLoading = true, errorMessage = null)
            when (val firmsRes = firmRepository.getFirms()) {
                is Resource.Success -> {
                    _uiState.value = _uiState.value.copy(firms = firmsRes.data ?: emptyList())
                }
                else -> Unit
            }
            when (val branchRes = firmRepository.getBranches()) {
                is Resource.Success -> {
                    _uiState.value = _uiState.value.copy(branches = branchRes.data ?: emptyList())
                }
                else -> Unit
            }
            when (val devRes = firmRepository.getDevices()) {
                is Resource.Success -> {
                    _uiState.value = _uiState.value.copy(devices = devRes.data ?: emptyList(), isLoading = false)
                }
                is Resource.Error -> {
                    _uiState.value = _uiState.value.copy(isLoading = false, errorMessage = devRes.message)
                }
                is Resource.Loading -> Unit
            }
        }
    }
}
