package uz.payday.app.presentation.workers

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
import uz.payday.app.domain.model.Worker
import uz.payday.app.domain.repository.FirmRepository
import uz.payday.app.domain.repository.WorkerRepository
import uz.payday.app.util.DateUtils
import uz.payday.app.util.ExcelGenerator
import uz.payday.app.util.FileOpener
import java.io.File
import javax.inject.Inject

data class WorkerListUiState(
    val workers: List<Worker> = emptyList(),
    val firms: List<Firm> = emptyList(),
    val branches: List<Branch> = emptyList(),
    val searchQuery: String = "",
    val selectedFirmId: Int? = null,
    val selectedBranchId: Int? = null,
    val selectedStatus: Int? = null,
    val selectedMonth: String = DateUtils.currentMonth(),
    val isLoading: Boolean = false,
    val isExporting: Boolean = false,
    val errorMessage: String? = null
)

data class WorkerDetailUiState(
    val worker: Worker? = null,
    val isLoading: Boolean = false,
    val errorMessage: String? = null,
    val isDeleted: Boolean = false
)

data class AddEditWorkerUiState(
    val id: Int? = null,
    val name: String = "",
    val branchId: Int? = null,
    val phone: String = "",
    val workTime: String = "09:00",
    val endTime: String = "18:00",
    val hourPrice: String = "0",
    val finePrice: String = "0",
    val salaryType: String = "hour",
    val comment: String = "",
    val avatarFile: File? = null,
    val branches: List<Branch> = emptyList(),
    val isLoading: Boolean = false,
    val isSaving: Boolean = false,
    val isSaved: Boolean = false,
    val errorMessage: String? = null
)

@HiltViewModel
class WorkerViewModel @Inject constructor(
    private val workerRepository: WorkerRepository,
    private val firmRepository: FirmRepository
) : ViewModel() {

    private val _listState = MutableStateFlow(WorkerListUiState())
    val listState: StateFlow<WorkerListUiState> = _listState.asStateFlow()

    private val _detailState = MutableStateFlow(WorkerDetailUiState())
    val detailState: StateFlow<WorkerDetailUiState> = _detailState.asStateFlow()

    private val _formState = MutableStateFlow(AddEditWorkerUiState())
    val formState: StateFlow<AddEditWorkerUiState> = _formState.asStateFlow()

    init {
        loadFilters()
        loadWorkers()
    }

    private fun loadFilters() {
        viewModelScope.launch {
            when (val firmsRes = firmRepository.getFirms()) {
                is Resource.Success -> {
                    _listState.value = _listState.value.copy(firms = firmsRes.data ?: emptyList())
                }
                else -> Unit
            }
            when (val branchesRes = firmRepository.getBranches()) {
                is Resource.Success -> {
                    val branchList = branchesRes.data ?: emptyList()
                    _listState.value = _listState.value.copy(branches = branchList)
                    _formState.value = _formState.value.copy(branches = branchList)
                }
                else -> Unit
            }
        }
    }

    fun loadWorkers() {
        viewModelScope.launch {
            _listState.value = _listState.value.copy(isLoading = true, errorMessage = null)
            val state = _listState.value
            when (val res = workerRepository.getWorkers(
                search = state.searchQuery.ifBlank { null },
                firmId = state.selectedFirmId,
                branchId = state.selectedBranchId,
                status = state.selectedStatus,
                month = state.selectedMonth
            )) {
                is Resource.Success -> {
                    _listState.value = _listState.value.copy(
                        workers = res.data ?: emptyList(),
                        isLoading = false
                    )
                }
                is Resource.Error -> {
                    _listState.value = _listState.value.copy(
                        isLoading = false,
                        errorMessage = res.message
                    )
                }
                is Resource.Loading -> Unit
            }
        }
    }

    fun onSearchQueryChanged(query: String) {
        _listState.value = _listState.value.copy(searchQuery = query)
        loadWorkers()
    }

    fun onBranchFilterChanged(branchId: Int?) {
        _listState.value = _listState.value.copy(selectedBranchId = branchId)
        loadWorkers()
    }

    fun exportToExcel(context: Context) {
        viewModelScope.launch {
            _listState.value = _listState.value.copy(isExporting = true)
            try {
                val file = ExcelGenerator.generateWorkersExcel(context, _listState.value.workers)
                _listState.value = _listState.value.copy(isExporting = false)
                FileOpener.shareFile(context, file, "Xodimlar Ro'yxati")
            } catch (e: Exception) {
                _listState.value = _listState.value.copy(
                    isExporting = false,
                    errorMessage = "Excel eksportda xatolik: ${e.localizedMessage}"
                )
            }
        }
    }

    fun loadWorkerDetail(workerId: Int) {
        viewModelScope.launch {
            _detailState.value = WorkerDetailUiState(isLoading = true)
            when (val res = workerRepository.getWorker(workerId)) {
                is Resource.Success -> {
                    _detailState.value = WorkerDetailUiState(worker = res.data, isLoading = false)
                }
                is Resource.Error -> {
                    _detailState.value = WorkerDetailUiState(
                        errorMessage = res.message ?: "Xodim ma'lumotlarini yuklashda xatolik",
                        isLoading = false
                    )
                }
                is Resource.Loading -> Unit
            }
        }
    }

    fun deleteWorker(workerId: Int) {
        viewModelScope.launch {
            _detailState.value = _detailState.value.copy(isLoading = true)
            when (val res = workerRepository.deleteWorker(workerId)) {
                is Resource.Success -> {
                    _detailState.value = _detailState.value.copy(isLoading = false, isDeleted = true)
                    loadWorkers()
                }
                is Resource.Error -> {
                    _detailState.value = _detailState.value.copy(
                        isLoading = false,
                        errorMessage = res.message ?: "O'chirishda xatolik"
                    )
                }
                is Resource.Loading -> Unit
            }
        }
    }

    fun initForm(workerId: Int?) {
        if (workerId == null) {
            _formState.value = AddEditWorkerUiState(
                branches = _listState.value.branches,
                branchId = _listState.value.branches.firstOrNull()?.id
            )
        } else {
            viewModelScope.launch {
                _formState.value = _formState.value.copy(isLoading = true, branches = _listState.value.branches)
                when (val res = workerRepository.getWorker(workerId)) {
                    is Resource.Success -> {
                        val w = res.data!!
                        _formState.value = AddEditWorkerUiState(
                            id = w.id,
                            name = w.name,
                            branchId = w.branchId,
                            phone = w.phone ?: "",
                            workTime = w.workTime ?: "09:00",
                            endTime = w.endTime ?: "18:00",
                            hourPrice = w.hourPrice.toString(),
                            finePrice = w.finePrice.toString(),
                            salaryType = w.salaryType ?: "hour",
                            comment = w.comment ?: "",
                            branches = _listState.value.branches,
                            isLoading = false
                        )
                    }
                    is Resource.Error -> {
                        _formState.value = _formState.value.copy(
                            isLoading = false,
                            errorMessage = res.message
                        )
                    }
                    is Resource.Loading -> Unit
                }
            }
        }
    }

    fun onFormNameChange(v: String) { _formState.value = _formState.value.copy(name = v) }
    fun onFormBranchChange(v: Int) { _formState.value = _formState.value.copy(branchId = v) }
    fun onFormPhoneChange(v: String) { _formState.value = _formState.value.copy(phone = v) }
    fun onFormWorkTimeChange(v: String) { _formState.value = _formState.value.copy(workTime = v) }
    fun onFormEndTimeChange(v: String) { _formState.value = _formState.value.copy(endTime = v) }
    fun onFormHourPriceChange(v: String) { _formState.value = _formState.value.copy(hourPrice = v) }
    fun onFormFinePriceChange(v: String) { _formState.value = _formState.value.copy(finePrice = v) }
    fun onFormSalaryTypeChange(v: String) { _formState.value = _formState.value.copy(salaryType = v) }
    fun onFormCommentChange(v: String) { _formState.value = _formState.value.copy(comment = v) }
    fun onFormAvatarChange(file: File) { _formState.value = _formState.value.copy(avatarFile = file) }

    fun saveWorker() {
        val f = _formState.value
        if (f.name.isBlank()) {
            _formState.value = f.copy(errorMessage = "Xodim ismini kiriting")
            return
        }
        if (f.branchId == null) {
            _formState.value = f.copy(errorMessage = "Filialni tanlang")
            return
        }

        viewModelScope.launch {
            _formState.value = f.copy(isSaving = true, errorMessage = null)
            val hp = f.hourPrice.toDoubleOrNull() ?: 0.0
            val fp = f.finePrice.toDoubleOrNull() ?: 0.0

            val result = if (f.id == null) {
                workerRepository.createWorker(
                    name = f.name.trim(),
                    branchId = f.branchId,
                    phone = f.phone.ifBlank { null },
                    workTime = f.workTime.ifBlank { null },
                    endTime = f.endTime.ifBlank { null },
                    hourPrice = hp,
                    finePrice = fp,
                    salaryType = f.salaryType,
                    comment = f.comment.ifBlank { null }
                )
            } else {
                workerRepository.updateWorker(
                    id = f.id,
                    name = f.name.trim(),
                    branchId = f.branchId,
                    phone = f.phone.ifBlank { null },
                    workTime = f.workTime.ifBlank { null },
                    endTime = f.endTime.ifBlank { null },
                    hourPrice = hp,
                    finePrice = fp,
                    salaryType = f.salaryType,
                    comment = f.comment.ifBlank { null }
                )
            }

            when (result) {
                is Resource.Success -> {
                    val savedWorker = result.data
                    if (f.avatarFile != null && savedWorker != null) {
                        workerRepository.uploadAvatar(savedWorker.id, f.avatarFile)
                    }
                    _formState.value = _formState.value.copy(isSaving = false, isSaved = true)
                    loadWorkers()
                }
                is Resource.Error -> {
                    _formState.value = _formState.value.copy(
                        isSaving = false,
                        errorMessage = result.message ?: "Saqlashda xatolik yuz berdi"
                    )
                }
                is Resource.Loading -> Unit
            }
        }
    }
}
