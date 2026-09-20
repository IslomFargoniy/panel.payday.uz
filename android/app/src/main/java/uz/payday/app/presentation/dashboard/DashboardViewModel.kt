package uz.payday.app.presentation.dashboard

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import uz.payday.app.domain.model.Branch
import uz.payday.app.domain.model.DashboardStats
import uz.payday.app.domain.model.Firm
import uz.payday.app.domain.model.Resource
import uz.payday.app.domain.repository.DashboardRepository
import uz.payday.app.domain.repository.FirmRepository
import uz.payday.app.util.DateUtils
import javax.inject.Inject

data class DashboardUiState(
    val stats: DashboardStats = DashboardStats(),
    val firms: List<Firm> = emptyList(),
    val branches: List<Branch> = emptyList(),
    val selectedFirmId: Int? = null,
    val selectedBranchId: Int? = null,
    val selectedDate: String = DateUtils.today(),
    val isLoading: Boolean = false,
    val errorMessage: String? = null
)

@HiltViewModel
class DashboardViewModel @Inject constructor(
    private val dashboardRepository: DashboardRepository,
    private val firmRepository: FirmRepository
) : ViewModel() {

    private val _uiState = MutableStateFlow(DashboardUiState())
    val uiState: StateFlow<DashboardUiState> = _uiState.asStateFlow()

    init {
        loadData()
    }

    fun loadData(forceRefresh: Boolean = false) {
        viewModelScope.launch {
            _uiState.value = _uiState.value.copy(isLoading = true, errorMessage = null)

            // Load firms & branches
            when (val firmResult = firmRepository.getFirms()) {
                is Resource.Success -> {
                    _uiState.value = _uiState.value.copy(firms = firmResult.data ?: emptyList())
                }
                else -> Unit
            }
            when (val branchResult = firmRepository.getBranches(_uiState.value.selectedFirmId)) {
                is Resource.Success -> {
                    _uiState.value = _uiState.value.copy(branches = branchResult.data ?: emptyList())
                }
                else -> Unit
            }

            // Load stats
            val state = _uiState.value
            when (val result = dashboardRepository.getDashboardStats(
                date = state.selectedDate,
                firmId = state.selectedFirmId,
                branchId = state.selectedBranchId,
                forceRefresh = forceRefresh
            )) {
                is Resource.Success -> {
                    _uiState.value = _uiState.value.copy(
                        stats = result.data ?: DashboardStats(),
                        isLoading = false
                    )
                }
                is Resource.Error -> {
                    _uiState.value = _uiState.value.copy(
                        isLoading = false,
                        errorMessage = result.message
                    )
                }
                is Resource.Loading -> Unit
            }
        }
    }

    fun onDateSelected(date: String) {
        _uiState.value = _uiState.value.copy(selectedDate = date)
        loadData()
    }

    fun onFirmSelected(firmId: Int?) {
        _uiState.value = _uiState.value.copy(selectedFirmId = firmId, selectedBranchId = null)
        loadData()
    }

    fun onBranchSelected(branchId: Int?) {
        _uiState.value = _uiState.value.copy(selectedBranchId = branchId)
        loadData()
    }
}
