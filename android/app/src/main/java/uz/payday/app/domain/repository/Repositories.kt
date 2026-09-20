package uz.payday.app.domain.repository

import kotlinx.coroutines.flow.Flow
import uz.payday.app.domain.model.*
import java.io.File

interface AuthRepository {
    suspend fun login(email: String, password: String): Resource<User>
    suspend fun getMe(): Resource<User>
    suspend fun logout(): Resource<Unit>
    suspend fun updateProfile(name: String, email: String, phone: String?): Resource<User>
    suspend fun changePassword(currentPassword: String, newPassword: String): Resource<Unit>
    fun getAuthToken(): Flow<String?>
    fun getLanguage(): Flow<String>
    suspend fun setLanguage(language: String)
    fun getThemeMode(): Flow<String>
    suspend fun setThemeMode(mode: String)
    fun isBiometricEnabled(): Flow<Boolean>
    suspend fun setBiometricEnabled(enabled: Boolean)
    fun getPinCode(): Flow<String?>
    suspend fun setPinCode(pin: String?)
    fun getBaseUrl(): Flow<String>
    suspend fun setBaseUrl(url: String)
}

interface DashboardRepository {
    suspend fun getDashboardStats(
        date: String? = null,
        month: String? = null,
        firmId: Int? = null,
        branchId: Int? = null,
        forceRefresh: Boolean = false
    ): Resource<DashboardStats>
}

interface WorkerRepository {
    suspend fun getWorkers(
        search: String? = null,
        firmId: Int? = null,
        branchId: Int? = null,
        status: Int? = null,
        page: Int = 1,
        date: String? = null,
        month: String? = null
    ): Resource<List<Worker>>

    suspend fun getWorker(id: Int): Resource<Worker>

    suspend fun createWorker(
        name: String,
        branchId: Int,
        phone: String?,
        workTime: String?,
        endTime: String?,
        hourPrice: Double?,
        finePrice: Double?,
        salaryType: String?,
        comment: String?
    ): Resource<Worker>

    suspend fun updateWorker(
        id: Int,
        name: String,
        branchId: Int,
        phone: String?,
        workTime: String?,
        endTime: String?,
        hourPrice: Double?,
        finePrice: Double?,
        salaryType: String?,
        comment: String?
    ): Resource<Worker>

    suspend fun deleteWorker(id: Int): Resource<Unit>

    suspend fun uploadAvatar(id: Int, imageFile: File): Resource<String>
}

interface AttendanceRepository {
    suspend fun getDailyAttendance(branchId: Int, date: String? = null): Resource<Map<String, Any>>
    suspend fun getMonthlyAttendance(branchId: Int?, month: String?, year: Int?): Resource<Map<String, Any>>
    suspend fun getAttendanceGrid(branchId: Int?, month: String?, year: Int?): Resource<Map<String, Any>>
}

interface SalaryRepository {
    suspend fun getSalaryReport(
        firmId: Int? = null,
        branchId: Int? = null,
        from: String? = null,
        to: String? = null,
        month: String? = null
    ): Resource<Map<String, Any>>

    suspend fun getSalaryList(
        workerId: Int? = null,
        from: String? = null,
        to: String? = null,
        page: Int = 1
    ): Resource<List<SalaryRecord>>

    suspend fun calculateSalary(
        workerId: Int,
        amount: Double,
        from: String,
        to: String,
        workedMinutes: Int?,
        breakMinutes: Int?,
        hourPrice: Double?
    ): Resource<Map<String, Any>>

    suspend fun getSalaryPayments(
        workerId: Int? = null,
        page: Int = 1
    ): Resource<List<SalaryPayment>>

    suspend fun createSalaryPayment(
        workerId: Int,
        amount: Double,
        date: String,
        comment: String?
    ): Resource<Map<String, Any>>
}

interface FirmRepository {
    suspend fun getFirms(): Resource<List<Firm>>
    suspend fun getBranches(firmId: Int? = null): Resource<List<Branch>>
    suspend fun getDevices(branchId: Int? = null): Resource<List<Map<String, Any>>>
}
