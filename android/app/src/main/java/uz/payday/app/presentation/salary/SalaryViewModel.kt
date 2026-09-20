package uz.payday.app.presentation.salary

import android.content.Context
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
import uz.payday.app.domain.model.SalaryPayment
import uz.payday.app.domain.model.SalaryRecord
import uz.payday.app.domain.repository.FirmRepository
import uz.payday.app.domain.repository.SalaryRepository
import uz.payday.app.util.DateUtils
import uz.payday.app.util.ExcelGenerator
import uz.payday.app.util.FileOpener
import javax.inject.Inject

data class SalaryUiState(
    val firms: List<Firm> = emptyList(),
    val branches: List<Branch> = emptyList(),
    val selectedFirmId: Int? = null,
    val selectedBranchId: Int? = null,
    val selectedMonth: String = DateUtils.currentMonth(),
    val reportData: Map<String, Any> = emptyMap(),
    val salaryRecords: List<SalaryRecord> = emptyList(),
    val payments: List<SalaryPayment> = emptyList(),
    val isLoading: Boolean = false,
    val isExporting: Boolean = false,
    val isCalculating: Boolean = false,
    val isPaymentSaving: Boolean = false,
    val errorMessage: String? = null,
    val successMessage: String? = null
)

@HiltViewModel
class SalaryViewModel @Inject constructor(
    private val salaryRepository: SalaryRepository,
    private val firmRepository: FirmRepository
) : ViewModel() {

    private val _uiState = MutableStateFlow(SalaryUiState())
    val uiState: StateFlow<SalaryUiState> = _uiState.asStateFlow()

    init {
        loadFilters()
        loadSalaryReport()
        loadSalaryPayments()
    }

    private fun loadFilters() {
        viewModelScope.launch {
            when (val firmsRes = firmRepository.getFirms()) {
                is Resource.Success -> {
                    _uiState.value = _uiState.value.copy(firms = firmsRes.data ?: emptyList())
                }
                else -> Unit
            }
            when (val branchesRes = firmRepository.getBranches()) {
                is Resource.Success -> {
                    _uiState.value = _uiState.value.copy(branches = branchesRes.data ?: emptyList())
                }
                else -> Unit
            }
        }
    }

    fun loadSalaryReport() {
        viewModelScope.launch {
            _uiState.value = _uiState.value.copy(isLoading = true, errorMessage = null)
            val state = _uiState.value
            when (val res = salaryRepository.getSalaryReport(
                firmId = state.selectedFirmId,
                branchId = state.selectedBranchId,
                month = state.selectedMonth
            )) {
                is Resource.Success -> {
                    _uiState.value = _uiState.value.copy(
                        reportData = res.data ?: emptyMap(),
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

    fun loadSalaryPayments(workerId: Int? = null) {
        viewModelScope.launch {
            when (val res = salaryRepository.getSalaryPayments(workerId)) {
                is Resource.Success -> {
                    _uiState.value = _uiState.value.copy(payments = res.data ?: emptyList())
                }
                else -> Unit
            }
        }
    }

    fun onBranchFilterChanged(branchId: Int?) {
        _uiState.value = _uiState.value.copy(selectedBranchId = branchId)
        loadSalaryReport()
    }

    fun calculateSalary(
        workerId: Int,
        amount: Double,
        from: String,
        to: String,
        workedMinutes: Int?,
        breakMinutes: Int?,
        hourPrice: Double?
    ) {
        viewModelScope.launch {
            _uiState.value = _uiState.value.copy(isCalculating = true, errorMessage = null)
            when (val res = salaryRepository.calculateSalary(
                workerId = workerId,
                amount = amount,
                from = from,
                to = to,
                workedMinutes = workedMinutes,
                breakMinutes = breakMinutes,
                hourPrice = hourPrice
            )) {
                is Resource.Success -> {
                    _uiState.value = _uiState.value.copy(
                        isCalculating = false,
                        successMessage = "Oylik muvaffaqiyatli hisoblandi"
                    )
                    loadSalaryReport()
                }
                is Resource.Error -> {
                    _uiState.value = _uiState.value.copy(
                        isCalculating = false,
                        errorMessage = res.message ?: "Hisoblashda xatolik yuz berdi"
                    )
                }
                is Resource.Loading -> Unit
            }
        }
    }

    fun recordPayment(workerId: Int, amount: Double, date: String, comment: String?) {
        viewModelScope.launch {
            _uiState.value = _uiState.value.copy(isPaymentSaving = true, errorMessage = null)
            when (val res = salaryRepository.createSalaryPayment(workerId, amount, date, comment)) {
                is Resource.Success -> {
                    _uiState.value = _uiState.value.copy(
                        isPaymentSaving = false,
                        successMessage = "To'lov muvaffaqiyatli amalga oshirildi"
                    )
                    loadSalaryPayments()
                    loadSalaryReport()
                }
                is Resource.Error -> {
                    _uiState.value = _uiState.value.copy(
                        isPaymentSaving = false,
                        errorMessage = res.message ?: "To'lovni saqlashda xatolik"
                    )
                }
                is Resource.Loading -> Unit
            }
        }
    }

    fun exportToExcel(context: Context) {
        viewModelScope.launch {
            _uiState.value = _uiState.value.copy(isExporting = true)
            try {
                val file = ExcelGenerator.generateAttendanceExcel(context, _uiState.value.reportData)
                _uiState.value = _uiState.value.copy(isExporting = false)
                FileOpener.shareFile(context, file, "Oylik Hisoboti")
            } catch (e: Exception) {
                _uiState.value = _uiState.value.copy(
                    isExporting = false,
                    errorMessage = "Excel eksportda xatolik: ${e.localizedMessage}"
                )
            }
        }
    }

    fun clearMessages() {
        _uiState.value = _uiState.value.copy(errorMessage = null, successMessage = null)
    }
}
