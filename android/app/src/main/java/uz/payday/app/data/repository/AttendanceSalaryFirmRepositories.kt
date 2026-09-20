package uz.payday.app.data.repository

import uz.payday.app.data.remote.ApiService
import uz.payday.app.data.remote.dto.BranchDto
import uz.payday.app.data.remote.dto.CalculateSalaryRequest
import uz.payday.app.data.remote.dto.CreateSalaryPaymentRequest
import uz.payday.app.data.remote.dto.FirmDto
import uz.payday.app.domain.model.*
import uz.payday.app.domain.repository.AttendanceRepository
import uz.payday.app.domain.repository.FirmRepository
import uz.payday.app.domain.repository.SalaryRepository
import javax.inject.Inject
import javax.inject.Singleton

@Singleton
class AttendanceRepositoryImpl @Inject constructor(
    private val apiService: ApiService
) : AttendanceRepository {

    override suspend fun getDailyAttendance(branchId: Int, date: String?): Resource<Map<String, Any>> {
        return try {
            val response = apiService.getDailyAttendance(branchId, date)
            if (response.isSuccessful && response.body()?.success == true) {
                Resource.Success(response.body()!!.data ?: emptyMap())
            } else {
                Resource.Error(response.body()?.message ?: "Failed to load daily attendance")
            }
        } catch (e: Exception) {
            Resource.Error(e.localizedMessage ?: "Network error")
        }
    }

    override suspend fun getMonthlyAttendance(branchId: Int?, month: String?, year: Int?): Resource<Map<String, Any>> {
        return try {
            val response = apiService.getMonthlyAttendance(branchId, month, year)
            if (response.isSuccessful && response.body()?.success == true) {
                Resource.Success(response.body()!!.data ?: emptyMap())
            } else {
                Resource.Error(response.body()?.message ?: "Failed to load monthly attendance")
            }
        } catch (e: Exception) {
            Resource.Error(e.localizedMessage ?: "Network error")
        }
    }

    override suspend fun getAttendanceGrid(branchId: Int?, month: String?, year: Int?): Resource<Map<String, Any>> {
        return try {
            val response = apiService.getAttendanceGrid(branchId, month, year)
            if (response.isSuccessful && response.body()?.success == true) {
                Resource.Success(response.body()!!.data ?: emptyMap())
            } else {
                Resource.Error(response.body()?.message ?: "Failed to load attendance grid")
            }
        } catch (e: Exception) {
            Resource.Error(e.localizedMessage ?: "Network error")
        }
    }
}

@Singleton
class SalaryRepositoryImpl @Inject constructor(
    private val apiService: ApiService
) : SalaryRepository {

    override suspend fun getSalaryReport(
        firmId: Int?,
        branchId: Int?,
        from: String?,
        to: String?,
        month: String?
    ): Resource<Map<String, Any>> {
        return try {
            val response = apiService.getSalaryReport(firmId, branchId, from, to, month)
            if (response.isSuccessful && response.body()?.success == true) {
                Resource.Success(response.body()!!.data ?: emptyMap())
            } else {
                Resource.Error(response.body()?.message ?: "Failed to load salary report")
            }
        } catch (e: Exception) {
            Resource.Error(e.localizedMessage ?: "Network error")
        }
    }

    @Suppress("UNCHECKED_CAST")
    override suspend fun getSalaryList(
        workerId: Int?,
        from: String?,
        to: String?,
        page: Int
    ): Resource<List<SalaryRecord>> {
        return try {
            val response = apiService.getSalaryList(workerId, from, to, page)
            if (response.isSuccessful && response.body()?.success == true) {
                val list = response.body()!!.data!!.data.mapNotNull { item ->
                    try {
                        SalaryRecord(
                            id = (item["id"] as? Number)?.toInt() ?: 0,
                            workerId = (item["worker_id"] as? Number)?.toInt() ?: 0,
                            workerName = (item["worker"] as? Map<String, Any>)?.get("name") as? String
                                ?: item["worker_name"] as? String ?: "",
                            amount = (item["amount"] as? Number)?.toDouble() ?: 0.0,
                            hourPrice = (item["hour_price"] as? Number)?.toDouble() ?: 0.0,
                            workedMinutes = (item["worked_minutes"] as? Number)?.toInt() ?: 0,
                            breakMinutes = (item["break_minutes"] as? Number)?.toInt() ?: 0,
                            fromDate = item["from_date"] as? String ?: "",
                            toDate = item["to_date"] as? String ?: "",
                            date = item["date"] as? String ?: "",
                            calculatedBy = item["calculated_by"] as? String
                        )
                    } catch (e: Exception) {
                        null
                    }
                }
                Resource.Success(list)
            } else {
                Resource.Error(response.body()?.message ?: "Failed to load salary calculations")
            }
        } catch (e: Exception) {
            Resource.Error(e.localizedMessage ?: "Network error")
        }
    }

    override suspend fun calculateSalary(
        workerId: Int,
        amount: Double,
        from: String,
        to: String,
        workedMinutes: Int?,
        breakMinutes: Int?,
        hourPrice: Double?
    ): Resource<Map<String, Any>> {
        return try {
            val req = CalculateSalaryRequest(
                workerId = workerId,
                amount = amount,
                from = from,
                to = to,
                workedMinutes = workedMinutes,
                breakMinutes = breakMinutes,
                hourPrice = hourPrice
            )
            val response = apiService.calculateSalary(req)
            if (response.isSuccessful && response.body()?.success == true) {
                Resource.Success(response.body()!!.data ?: emptyMap())
            } else {
                Resource.Error(response.body()?.message ?: "Salary calculation failed")
            }
        } catch (e: Exception) {
            Resource.Error(e.localizedMessage ?: "Network error")
        }
    }

    @Suppress("UNCHECKED_CAST")
    override suspend fun getSalaryPayments(
        workerId: Int?,
        page: Int
    ): Resource<List<SalaryPayment>> {
        return try {
            val response = apiService.getSalaryPayments(workerId, page)
            if (response.isSuccessful && response.body()?.success == true) {
                val list = response.body()!!.data!!.data.mapNotNull { item ->
                    try {
                        SalaryPayment(
                            id = (item["id"] as? Number)?.toInt() ?: 0,
                            workerId = (item["worker_id"] as? Number)?.toInt() ?: 0,
                            workerName = (item["worker"] as? Map<String, Any>)?.get("name") as? String
                                ?: item["worker_name"] as? String ?: "",
                            amount = (item["amount"] as? Number)?.toDouble() ?: 0.0,
                            date = item["date"] as? String ?: "",
                            comment = item["comment"] as? String,
                            createdBy = item["created_by"] as? String
                        )
                    } catch (e: Exception) {
                        null
                    }
                }
                Resource.Success(list)
            } else {
                Resource.Error(response.body()?.message ?: "Failed to load salary payments")
            }
        } catch (e: Exception) {
            Resource.Error(e.localizedMessage ?: "Network error")
        }
    }

    override suspend fun createSalaryPayment(
        workerId: Int,
        amount: Double,
        date: String,
        comment: String?
    ): Resource<Map<String, Any>> {
        return try {
            val req = CreateSalaryPaymentRequest(
                workerId = workerId,
                amount = amount,
                date = date,
                comment = comment
            )
            val response = apiService.createSalaryPayment(req)
            if (response.isSuccessful && response.body()?.success == true) {
                Resource.Success(response.body()!!.data ?: emptyMap())
            } else {
                Resource.Error(response.body()?.message ?: "Failed to record payment")
            }
        } catch (e: Exception) {
            Resource.Error(e.localizedMessage ?: "Network error")
        }
    }
}

@Singleton
class FirmRepositoryImpl @Inject constructor(
    private val apiService: ApiService
) : FirmRepository {

    private fun mapFirmDto(dto: FirmDto): Firm = Firm(
        id = dto.id,
        name = dto.name,
        address = dto.address,
        branchLimit = dto.branchLimit ?: 0,
        validDate = dto.validDate,
        status = dto.status ?: 1
    )

    private fun mapBranchDto(dto: BranchDto): Branch = Branch(
        id = dto.id,
        firmId = dto.firmId ?: 0,
        name = dto.name,
        address = dto.address,
        latitude = dto.latitude,
        longitude = dto.longitude,
        hourPrice = dto.hourPrice ?: 0.0,
        finePrice = dto.finePrice ?: 0.0,
        firm = dto.firm?.let { mapFirmDto(it) }
    )

    override suspend fun getFirms(): Resource<List<Firm>> {
        return try {
            val response = apiService.getFirms()
            if (response.isSuccessful && response.body()?.success == true) {
                val firms = response.body()!!.data!!.map { mapFirmDto(it) }
                Resource.Success(firms)
            } else {
                Resource.Error(response.body()?.message ?: "Failed to load firms")
            }
        } catch (e: Exception) {
            Resource.Error(e.localizedMessage ?: "Network error")
        }
    }

    override suspend fun getBranches(firmId: Int?): Resource<List<Branch>> {
        return try {
            val response = apiService.getBranches(firmId)
            if (response.isSuccessful && response.body()?.success == true) {
                val branches = response.body()!!.data!!.map { mapBranchDto(it) }
                Resource.Success(branches)
            } else {
                Resource.Error(response.body()?.message ?: "Failed to load branches")
            }
        } catch (e: Exception) {
            Resource.Error(e.localizedMessage ?: "Network error")
        }
    }

    override suspend fun getDevices(branchId: Int?): Resource<List<Map<String, Any>>> {
        return try {
            val response = apiService.getDevices(branchId)
            if (response.isSuccessful && response.body()?.success == true) {
                Resource.Success(response.body()!!.data ?: emptyList())
            } else {
                Resource.Error(response.body()?.message ?: "Failed to load devices")
            }
        } catch (e: Exception) {
            Resource.Error(e.localizedMessage ?: "Network error")
        }
    }
}
