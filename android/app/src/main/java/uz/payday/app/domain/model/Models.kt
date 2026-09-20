package uz.payday.app.domain.model

data class User(
    val id: Int,
    val name: String,
    val email: String,
    val phone: String? = null,
    val roles: List<String> = emptyList()
)

data class DashboardStats(
    val totalWorkers: Int = 0,
    val inTime: Int = 0,
    val late: Int = 0,
    val notCome: Int = 0,
    val totalFirms: Int = 0,
    val totalBranches: Int = 0,
    val date: String = "",
    val month: String = ""
)

data class Firm(
    val id: Int,
    val name: String,
    val address: String? = null,
    val branchLimit: Int = 0,
    val validDate: String? = null,
    val status: Int = 1,
    val branches: List<Branch> = emptyList()
)

data class Branch(
    val id: Int,
    val firmId: Int = 0,
    val name: String,
    val address: String? = null,
    val latitude: Double? = null,
    val longitude: Double? = null,
    val hourPrice: Double = 0.0,
    val finePrice: Double = 0.0,
    val firm: Firm? = null
)

data class Worker(
    val id: Int,
    val branchId: Int = 0,
    val name: String,
    val phone: String? = null,
    val avatar: String? = null,
    val workTime: String? = "09:00",
    val endTime: String? = "18:00",
    val hourPrice: Double = 0.0,
    val finePrice: Double = 0.0,
    val salaryType: String? = "hour",
    val comment: String? = null,
    val status: Int = 1,
    val branch: Branch? = null,
    val lateMinutes: Int? = 0,
    val breakMinutes: Int? = 0,
    val workedMinutes: Int? = 0,
    val lateDays: Int? = 0,
    val workedDays: Int? = 0,
    val workDays: Int? = 0,
    val accessEvents: List<AccessEvent> = emptyList()
)

data class AccessEvent(
    val id: Int,
    val employeeNoString: String? = null,
    val attendanceStatus: String? = null, // "checkIn", "checkOut"
    val workTime: String? = null,
    val createdAt: String
)

data class AttendanceRecord(
    val id: Int,
    val workerId: Int,
    val workerName: String,
    val phone: String?,
    val branchName: String,
    val firmName: String,
    val date: String,
    val checkIn: String?,
    val checkOut: String?,
    val lateDuration: String?,
    val workedDuration: String?
)

data class SalaryRecord(
    val id: Int,
    val workerId: Int,
    val workerName: String?,
    val amount: Double,
    val hourPrice: Double,
    val workedMinutes: Int,
    val breakMinutes: Int,
    val fromDate: String,
    val toDate: String,
    val date: String,
    val calculatedBy: String?
)

data class SalaryPayment(
    val id: Int,
    val workerId: Int,
    val workerName: String?,
    val amount: Double,
    val date: String,
    val comment: String?,
    val createdBy: String?
)
