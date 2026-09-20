package uz.payday.app.data.remote

import okhttp3.MultipartBody
import retrofit2.Response
import retrofit2.http.*
import uz.payday.app.data.remote.dto.*

interface ApiService {

    // --- Authentication & User ---
    @POST("auth/login")
    suspend fun login(
        @Body request: LoginRequest
    ): Response<ApiResponse<LoginResponseData>>

    @GET("auth/me")
    suspend fun getMe(): Response<ApiResponse<UserDto>>

    @POST("auth/logout")
    suspend fun logout(): Response<ApiResponse<Unit>>

    @PUT("auth/profile")
    suspend fun updateProfile(
        @Body body: Map<String, String>
    ): Response<ApiResponse<UserDto>>

    @PUT("auth/change-password")
    suspend fun changePassword(
        @Body body: Map<String, String>
    ): Response<ApiResponse<Unit>>

    // --- Dashboard ---
    @GET("dashboard")
    suspend fun getDashboard(
        @Query("date") date: String? = null,
        @Query("month") month: String? = null,
        @Query("firm_id") firmId: Int? = null,
        @Query("branch_id") branchId: Int? = null
    ): Response<ApiResponse<DashboardResponseData>>

    // --- Workers ---
    @GET("workers")
    suspend fun getWorkers(
        @Query("search") search: String? = null,
        @Query("firm_id") firmId: Int? = null,
        @Query("branch_id") branchId: Int? = null,
        @Query("status") status: Int? = null,
        @Query("page") page: Int = 1,
        @Query("per_page") perPage: Int = 30,
        @Query("date") date: String? = null,
        @Query("month") month: String? = null
    ): Response<ApiResponse<PaginatedData<WorkerDto>>>

    @POST("workers")
    suspend fun createWorker(
        @Body request: CreateWorkerRequest
    ): Response<ApiResponse<WorkerDto>>

    @GET("workers/{id}")
    suspend fun getWorker(
        @Path("id") id: Int
    ): Response<ApiResponse<WorkerDto>>

    @PUT("workers/{id}")
    suspend fun updateWorker(
        @Path("id") id: Int,
        @Body request: CreateWorkerRequest
    ): Response<ApiResponse<WorkerDto>>

    @DELETE("workers/{id}")
    suspend fun deleteWorker(
        @Path("id") id: Int
    ): Response<ApiResponse<Unit>>

    @Multipart
    @POST("workers/{id}/avatar")
    suspend fun uploadWorkerAvatar(
        @Path("id") id: Int,
        @Part avatar: MultipartBody.Part
    ): Response<ApiResponse<Map<String, Any>>>

    // --- Attendance ---
    @GET("attendance/daily/{branchId}")
    suspend fun getDailyAttendance(
        @Path("branchId") branchId: Int,
        @Query("date") date: String? = null
    ): Response<ApiResponse<Map<String, Any>>>

    @GET("attendance/monthly")
    suspend fun getMonthlyAttendance(
        @Query("branch_id") branchId: Int? = null,
        @Query("month") month: String? = null,
        @Query("year") year: Int? = null
    ): Response<ApiResponse<Map<String, Any>>>

    @GET("attendance/grid")
    suspend fun getAttendanceGrid(
        @Query("branch_id") branchId: Int? = null,
        @Query("month") month: String? = null,
        @Query("year") year: Int? = null
    ): Response<ApiResponse<Map<String, Any>>>

    // --- Salary & Payments ---
    @GET("salary/report")
    suspend fun getSalaryReport(
        @Query("firm_id") firmId: Int? = null,
        @Query("branch_id") branchId: Int? = null,
        @Query("from") from: String? = null,
        @Query("to") to: String? = null,
        @Query("month") month: String? = null
    ): Response<ApiResponse<Map<String, Any>>>

    @GET("salary/list")
    suspend fun getSalaryList(
        @Query("worker_id") workerId: Int? = null,
        @Query("from") from: String? = null,
        @Query("to") to: String? = null,
        @Query("page") page: Int = 1
    ): Response<ApiResponse<PaginatedData<Map<String, Any>>>>

    @POST("salary/calculate")
    suspend fun calculateSalary(
        @Body request: CalculateSalaryRequest
    ): Response<ApiResponse<Map<String, Any>>>

    @GET("salary/payments")
    suspend fun getSalaryPayments(
        @Query("worker_id") workerId: Int? = null,
        @Query("page") page: Int = 1
    ): Response<ApiResponse<PaginatedData<Map<String, Any>>>>

    @POST("salary/payments")
    suspend fun createSalaryPayment(
        @Body request: CreateSalaryPaymentRequest
    ): Response<ApiResponse<Map<String, Any>>>

    // --- Firms, Branches, Devices ---
    @GET("firms")
    suspend fun getFirms(): Response<ApiResponse<List<FirmDto>>>

    @GET("branches")
    suspend fun getBranches(
        @Query("firm_id") firmId: Int? = null
    ): Response<ApiResponse<List<BranchDto>>>

    @GET("devices")
    suspend fun getDevices(
        @Query("branch_id") branchId: Int? = null
    ): Response<ApiResponse<List<Map<String, Any>>>>
}
