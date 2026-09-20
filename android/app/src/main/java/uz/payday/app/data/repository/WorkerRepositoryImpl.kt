package uz.payday.app.data.repository

import okhttp3.MediaType.Companion.toMediaTypeOrNull
import okhttp3.MultipartBody
import okhttp3.RequestBody.Companion.asRequestBody
import uz.payday.app.data.local.dao.WorkerDao
import uz.payday.app.data.local.entity.WorkerEntity
import uz.payday.app.data.remote.ApiService
import uz.payday.app.data.remote.dto.AccessEventDto
import uz.payday.app.data.remote.dto.BranchDto
import uz.payday.app.data.remote.dto.CreateWorkerRequest
import uz.payday.app.data.remote.dto.FirmDto
import uz.payday.app.data.remote.dto.WorkerDto
import uz.payday.app.domain.model.AccessEvent
import uz.payday.app.domain.model.Branch
import uz.payday.app.domain.model.Firm
import uz.payday.app.domain.model.Resource
import uz.payday.app.domain.model.Worker
import uz.payday.app.domain.repository.WorkerRepository
import java.io.File
import javax.inject.Inject
import javax.inject.Singleton

@Singleton
class WorkerRepositoryImpl @Inject constructor(
    private val apiService: ApiService,
    private val workerDao: WorkerDao
) : WorkerRepository {

    private fun mapWorkerDtoToDomain(dto: WorkerDto): Worker {
        return Worker(
            id = dto.id,
            branchId = dto.branchId ?: 0,
            name = dto.name,
            phone = dto.phone,
            avatar = dto.avatar,
            workTime = dto.workTime,
            endTime = dto.endTime,
            hourPrice = dto.hourPrice ?: 0.0,
            finePrice = dto.finePrice ?: 0.0,
            salaryType = dto.salaryType,
            comment = dto.comment,
            status = dto.status ?: 1,
            branch = dto.branch?.let { b ->
                Branch(
                    id = b.id,
                    firmId = b.firmId ?: 0,
                    name = b.name,
                    address = b.address,
                    latitude = b.latitude,
                    longitude = b.longitude,
                    hourPrice = b.hourPrice ?: 0.0,
                    finePrice = b.finePrice ?: 0.0,
                    firm = b.firm?.let { f ->
                        Firm(
                            id = f.id,
                            name = f.name,
                            address = f.address,
                            branchLimit = f.branchLimit ?: 0,
                            validDate = f.validDate,
                            status = f.status ?: 1
                        )
                    }
                )
            },
            lateMinutes = dto.lateMinutes,
            breakMinutes = dto.breakMinutes,
            workedMinutes = dto.workedMinutes,
            lateDays = dto.lateDays,
            workedDays = dto.workedDays,
            workDays = dto.workDays,
            accessEvents = dto.accessEvents?.map { e ->
                AccessEvent(
                    id = e.id,
                    employeeNoString = e.employeeNoString,
                    attendanceStatus = e.attendanceStatus,
                    workTime = e.workTime,
                    createdAt = e.createdAt
                )
            } ?: emptyList()
        )
    }

    override suspend fun getWorkers(
        search: String?,
        firmId: Int?,
        branchId: Int?,
        status: Int?,
        page: Int,
        date: String?,
        month: String?
    ): Resource<List<Worker>> {
        return try {
            val response = apiService.getWorkers(search, firmId, branchId, status, page, 50, date, month)
            if (response.isSuccessful && response.body()?.success == true) {
                val paginated = response.body()!!.data!!
                val workers = paginated.data.map { mapWorkerDtoToDomain(it) }

                // Cache in Room
                val entities = workers.map { w ->
                    WorkerEntity(
                        id = w.id,
                        branchId = w.branchId,
                        name = w.name,
                        phone = w.phone,
                        avatar = w.avatar,
                        workTime = w.workTime,
                        endTime = w.endTime,
                        hourPrice = w.hourPrice,
                        finePrice = w.finePrice,
                        salaryType = w.salaryType,
                        comment = w.comment,
                        status = w.status,
                        branchName = w.branch?.name,
                        lateMinutes = w.lateMinutes,
                        breakMinutes = w.breakMinutes,
                        workedMinutes = w.workedMinutes,
                        lateDays = w.lateDays,
                        workedDays = w.workedDays,
                        workDays = w.workDays
                    )
                }
                workerDao.insertWorkers(entities)

                Resource.Success(workers)
            } else {
                Resource.Error(response.body()?.message ?: "Failed to fetch workers")
            }
        } catch (e: Exception) {
            Resource.Error(e.localizedMessage ?: "Network error")
        }
    }

    override suspend fun getWorker(id: Int): Resource<Worker> {
        return try {
            val response = apiService.getWorker(id)
            if (response.isSuccessful && response.body()?.success == true) {
                val worker = mapWorkerDtoToDomain(response.body()!!.data!!)
                Resource.Success(worker)
            } else {
                Resource.Error(response.body()?.message ?: "Worker not found")
            }
        } catch (e: Exception) {
            Resource.Error(e.localizedMessage ?: "Network error")
        }
    }

    override suspend fun createWorker(
        name: String,
        branchId: Int,
        phone: String?,
        workTime: String?,
        endTime: String?,
        hourPrice: Double?,
        finePrice: Double?,
        salaryType: String?,
        comment: String?
    ): Resource<Worker> {
        return try {
            val req = CreateWorkerRequest(
                name = name,
                branchId = branchId,
                phone = phone,
                workTime = workTime,
                endTime = endTime,
                hourPrice = hourPrice,
                finePrice = finePrice,
                salaryType = salaryType,
                comment = comment
            )
            val response = apiService.createWorker(req)
            if (response.isSuccessful && response.body()?.success == true) {
                val worker = mapWorkerDtoToDomain(response.body()!!.data!!)
                Resource.Success(worker)
            } else {
                Resource.Error(response.body()?.message ?: "Failed to create worker")
            }
        } catch (e: Exception) {
            Resource.Error(e.localizedMessage ?: "Network error")
        }
    }

    override suspend fun updateWorker(
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
    ): Resource<Worker> {
        return try {
            val req = CreateWorkerRequest(
                name = name,
                branchId = branchId,
                phone = phone,
                workTime = workTime,
                endTime = endTime,
                hourPrice = hourPrice,
                finePrice = finePrice,
                salaryType = salaryType,
                comment = comment
            )
            val response = apiService.updateWorker(id, req)
            if (response.isSuccessful && response.body()?.success == true) {
                val worker = mapWorkerDtoToDomain(response.body()!!.data!!)
                Resource.Success(worker)
            } else {
                Resource.Error(response.body()?.message ?: "Failed to update worker")
            }
        } catch (e: Exception) {
            Resource.Error(e.localizedMessage ?: "Network error")
        }
    }

    override suspend fun deleteWorker(id: Int): Resource<Unit> {
        return try {
            val response = apiService.deleteWorker(id)
            if (response.isSuccessful && response.body()?.success == true) {
                workerDao.deleteWorker(id)
                Resource.Success(Unit)
            } else {
                Resource.Error(response.body()?.message ?: "Failed to delete worker")
            }
        } catch (e: Exception) {
            Resource.Error(e.localizedMessage ?: "Network error")
        }
    }

    override suspend fun uploadAvatar(id: Int, imageFile: File): Resource<String> {
        return try {
            val reqBody = imageFile.asRequestBody("image/*".toMediaTypeOrNull())
            val part = MultipartBody.Part.createFormData("avatar", imageFile.name, reqBody)
            val response = apiService.uploadWorkerAvatar(id, part)
            if (response.isSuccessful && response.body()?.success == true) {
                val url = response.body()?.data?.get("avatar_url") as? String ?: ""
                Resource.Success(url)
            } else {
                Resource.Error(response.body()?.message ?: "Failed to upload avatar")
            }
        } catch (e: Exception) {
            Resource.Error(e.localizedMessage ?: "Network error")
        }
    }
}
