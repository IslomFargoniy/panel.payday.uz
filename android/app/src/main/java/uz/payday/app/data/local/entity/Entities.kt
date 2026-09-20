package uz.payday.app.data.local.entity

import androidx.room.Entity
import androidx.room.PrimaryKey
import uz.payday.app.domain.model.Worker

@Entity(tableName = "workers")
data class WorkerEntity(
    @PrimaryKey val id: Int,
    val branchId: Int,
    val name: String,
    val phone: String?,
    val avatar: String?,
    val workTime: String?,
    val endTime: String?,
    val hourPrice: Double,
    val finePrice: Double,
    val salaryType: String?,
    val comment: String?,
    val status: Int,
    val branchName: String?,
    val lateMinutes: Int?,
    val breakMinutes: Int?,
    val workedMinutes: Int?,
    val lateDays: Int?,
    val workedDays: Int?,
    val workDays: Int?
) {
    fun toDomain(): Worker = Worker(
        id = id,
        branchId = branchId,
        name = name,
        phone = phone,
        avatar = avatar,
        workTime = workTime,
        endTime = endTime,
        hourPrice = hourPrice,
        finePrice = finePrice,
        salaryType = salaryType,
        comment = comment,
        status = status,
        lateMinutes = lateMinutes,
        breakMinutes = breakMinutes,
        workedMinutes = workedMinutes,
        lateDays = lateDays,
        workedDays = workedDays,
        workDays = workDays
    )
}

@Entity(tableName = "dashboard_stats")
data class DashboardStatsEntity(
    @PrimaryKey val id: Int = 1,
    val totalWorkers: Int,
    val inTime: Int,
    val late: Int,
    val notCome: Int,
    val totalFirms: Int,
    val totalBranches: Int,
    val date: String,
    val month: String,
    val updatedAt: Long = System.currentTimeMillis()
)
