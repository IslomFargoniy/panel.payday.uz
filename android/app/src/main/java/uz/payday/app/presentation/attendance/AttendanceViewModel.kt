package uz.payday.app.presentation.attendance

import android.content.Context
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import uz.payday.app.domain.model.Branch
import uz.payday.app.domain.model.Resource
import uz.payday.app.domain.repository.AttendanceRepository
import uz.payday.app.domain.repository.FirmRepository
import uz.payday.app.util.DateUtils
import uz.payday.app.util.ExcelGenerator
import uz.payday.app.util.FileOpener
import java.util.Calendar
import javax.inject.Inject

data class AttendanceUiState(
    val branches: List<Branch> = emptyList(),
    val selectedBranchId: Int? = null,
    val selectedDate: String = DateUtils.today(),
    val selectedMonth: String = DateUtils.currentMonth(),
    val dailyData: Map<String, Any> = emptyMap(),
    val monthlyData: Map<String, Any> = emptyMap(),
    val gridData: Map<String, Any> = emptyMap(),
    val isLoading: Boolean = false,
    val isExporting: Boolean = false,
    val errorMessage: String? = null
)

@HiltViewModel
class AttendanceViewModel @Inject constructor(
    private val attendanceRepository: AttendanceRepository,
    private val firmRepository: FirmRepository
) : ViewModel() {

    private val _uiState = MutableStateFlow(AttendanceUiState())
    val uiState: StateFlow<AttendanceUiState> = _uiState.asStateFlow()

    init {
        loadBranches()
    }

    private fun loadBranches() {
        viewModelScope.launch {
            when (val res = firmRepository.getBranches()) {
                is Resource.Success -> {
                    val branchList = res.data ?: emptyList()
                    val firstBranchId = branchList.firstOrNull()?.id
                    _uiState.value = _uiState.value.copy(
                        branches = branchList,
                        selectedBranchId = firstBranchId
                    )
                    if (firstBranchId != null) {
                        loadDailyAttendance(firstBranchId)
                    }
                }
                else -> Unit
            }
        }
    }

    fun loadDailyAttendance(branchId: Int? = _uiState.value.selectedBranchId) {
        if (branchId == null) return
        viewModelScope.launch {
            _uiState.value = _uiState.value.copy(isLoading = true, errorMessage = null)
            when (val res = attendanceRepository.getDailyAttendance(branchId, _uiState.value.selectedDate)) {
                is Resource.Success -> {
                    _uiState.value = _uiState.value.copy(
                        dailyData = res.data ?: emptyMap(),
                        isLoading = false
                    )
                }
                is Resource.Error -> {
                    _uiState.value = _uiState.value.copy(
                        isLoading = false,
                        errorMessage = res.message
                    )
                }
                is Resource.Loading -> Unit
            }
        }
    }

    fun loadMonthlyAttendance(branchId: Int? = _uiState.value.selectedBranchId) {
        viewModelScope.launch {
            _uiState.value = _uiState.value.copy(isLoading = true, errorMessage = null)
            val parts = _uiState.value.selectedMonth.split("-")
            val year = parts.getOrNull(0)?.toIntOrNull() ?: Calendar.getInstance().get(Calendar.YEAR)
            val month = parts.getOrNull(1) ?: "01"

            when (val res = attendanceRepository.getMonthlyAttendance(branchId, month, year)) {
                is Resource.Success -> {
                    _uiState.value = _uiState.value.copy(
                        monthlyData = res.data ?: emptyMap(),
                        isLoading = false
                    )
                }
                is Resource.Error -> {
                    _uiState.value = _uiState.value.copy(
                        isLoading = false,
                        errorMessage = res.message
                    )
                }
                is Resource.Loading -> Unit
            }
        }
    }

    fun loadAttendanceGrid(branchId: Int? = _uiState.value.selectedBranchId) {
        viewModelScope.launch {
            _uiState.value = _uiState.value.copy(isLoading = true, errorMessage = null)
            val parts = _uiState.value.selectedMonth.split("-")
            val year = parts.getOrNull(0)?.toIntOrNull() ?: Calendar.getInstance().get(Calendar.YEAR)
            val month = parts.getOrNull(1) ?: "01"

            when (val res = attendanceRepository.getAttendanceGrid(branchId, month, year)) {
                is Resource.Success -> {
                    _uiState.value = _uiState.value.copy(
                        gridData = res.data ?: emptyMap(),
                        isLoading = false
                    )
                }
                is Resource.Error -> {
                    _uiState.value = _uiState.value.copy(
                        isLoading = false,
                        errorMessage = res.message
                    )
                }
                is Resource.Loading -> Unit
            }
        }
    }

    fun onBranchSelected(branchId: Int) {
        _uiState.value = _uiState.value.copy(selectedBranchId = branchId)
        loadDailyAttendance(branchId)
    }

    fun onDateSelected(date: String) {
        _uiState.value = _uiState.value.copy(selectedDate = date)
        loadDailyAttendance()
    }

    fun onMonthSelected(month: String) {
        _uiState.value = _uiState.value.copy(selectedMonth = month)
        loadMonthlyAttendance()
        loadAttendanceGrid()
    }

    fun exportToExcel(context: Context) {
        viewModelScope.launch {
            _uiState.value = _uiState.value.copy(isExporting = true)
            try {
                val file = ExcelGenerator.generateAttendanceExcel(context, _uiState.value.dailyData)
                _uiState.value = _uiState.value.copy(isExporting = false)
                FileOpener.shareFile(context, file, "Davomat hisoboti")
            } catch (e: Exception) {
                _uiState.value = _uiState.value.copy(
                    isExporting = false,
                    errorMessage = "Excel eksportda xatolik: ${e.localizedMessage}"
                )
            }
        }
    }
}
