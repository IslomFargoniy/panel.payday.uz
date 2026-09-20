package uz.payday.app.data.remote.dto

import com.google.gson.annotations.SerializedName

data class ApiResponse<T>(
    @SerializedName("success") val success: Boolean,
    @SerializedName("message") val message: String? = null,
    @SerializedName("data") val data: T? = null
)

data class PaginatedData<T>(
    @SerializedName("current_page") val currentPage: Int,
    @SerializedName("data") val data: List<T>,
    @SerializedName("first_page_url") val firstPageUrl: String?,
    @SerializedName("last_page") val lastPage: Int,
    @SerializedName("per_page") val perPage: Int,
    @SerializedName("total") val total: Int
)

data class LoginRequest(
    @SerializedName("email") val email: String,
    @SerializedName("password") val password: String,
    @SerializedName("device_name") val deviceName: String = "android"
)

data class LoginResponseData(
    @SerializedName("token") val token: String,
    @SerializedName("user") val user: UserDto
)

data class UserDto(
    @SerializedName("id") val id: Int,
    @SerializedName("name") val name: String,
    @SerializedName("email") val email: String,
    @SerializedName("phone") val phone: String?,
    @SerializedName("roles") val roles: List<String>? = emptyList()
)

data class DashboardStatsDto(
    @SerializedName("total_workers") val totalWorkers: Int,
    @SerializedName("in_time") val inTime: Int,
    @SerializedName("late") val late: Int,
    @SerializedName("not_come") val notCome: Int,
    @SerializedName("total_firms") val totalFirms: Int,
    @SerializedName("total_branches") val totalBranches: Int,
    @SerializedName("date") val date: String,
    @SerializedName("month") val month: String
)

data class DashboardResponseData(
    @SerializedName("stats") val stats: DashboardStatsDto,
    @SerializedName("firms") val firms: List<FirmDto>,
    @SerializedName("branches") val branches: List<BranchDto>
)

data class FirmDto(
    @SerializedName("id") val id: Int,
    @SerializedName("name") val name: String,
    @SerializedName("address") val address: String?,
    @SerializedName("branch_limit") val branchLimit: Int?,
    @SerializedName("valid_date") val validDate: String?,
    @SerializedName("status") val status: Int?
)

data class BranchDto(
    @SerializedName("id") val id: Int,
    @SerializedName("firm_id") val firmId: Int?,
    @SerializedName("name") val name: String,
    @SerializedName("address") val address: String?,
    @SerializedName("latitude") val latitude: Double?,
    @SerializedName("longitude") val longitude: Double?,
    @SerializedName("hour_price") val hourPrice: Double?,
    @SerializedName("fine_price") val finePrice: Double?,
    @SerializedName("firm") val firm: FirmDto?
)

data class WorkerDto(
    @SerializedName("id") val id: Int,
    @SerializedName("branch_id") val branchId: Int?,
    @SerializedName("name") val name: String,
    @SerializedName("phone") val phone: String?,
    @SerializedName("avatar") val avatar: String?,
    @SerializedName("work_time") val workTime: String?,
    @SerializedName("end_time") val endTime: String?,
    @SerializedName("hour_price") val hourPrice: Double?,
    @SerializedName("fine_price") val finePrice: Double?,
    @SerializedName("salary_type") val salaryType: String?,
    @SerializedName("comment") val comment: String?,
    @SerializedName("status") val status: Int?,
    @SerializedName("branch") val branch: BranchDto?,
    @SerializedName("late_minutes") val lateMinutes: Int?,
    @SerializedName("break_minutes") val breakMinutes: Int?,
    @SerializedName("worked_minutes") val workedMinutes: Int?,
    @SerializedName("late_days") val lateDays: Int?,
    @SerializedName("worked_days") val workedDays: Int?,
    @SerializedName("work_days") val workDays: Int?,
    @SerializedName("hikvision_access_events") val accessEvents: List<AccessEventDto>?
)

data class AccessEventDto(
    @SerializedName("id") val id: Int,
    @SerializedName("employeeNoString") val employeeNoString: String?,
    @SerializedName("attendanceStatus") val attendanceStatus: String?,
    @SerializedName("work_time") val workTime: String?,
    @SerializedName("created_at") val createdAt: String
)

data class CreateWorkerRequest(
    @SerializedName("name") val name: String,
    @SerializedName("branch_id") val branchId: Int,
    @SerializedName("phone") val phone: String?,
    @SerializedName("work_time") val workTime: String?,
    @SerializedName("end_time") val endTime: String?,
    @SerializedName("hour_price") val hourPrice: Double?,
    @SerializedName("fine_price") val finePrice: Double?,
    @SerializedName("salary_type") val salaryType: String?,
    @SerializedName("comment") val comment: String?
)

data class CalculateSalaryRequest(
    @SerializedName("worker_id") val workerId: Int,
    @SerializedName("amount") val amount: Double,
    @SerializedName("from") val from: String,
    @SerializedName("to") val to: String,
    @SerializedName("worked_minutes") val workedMinutes: Int?,
    @SerializedName("break_minutes") val breakMinutes: Int?,
    @SerializedName("hour_price") val hourPrice: Double?
)

data class CreateSalaryPaymentRequest(
    @SerializedName("worker_id") val workerId: Int,
    @SerializedName("amount") val amount: Double,
    @SerializedName("date") val date: String,
    @SerializedName("comment") val comment: String?
)
