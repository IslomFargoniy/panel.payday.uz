package uz.payday.app.data.repository

import kotlinx.coroutines.flow.Flow
import uz.payday.app.data.local.dao.DashboardDao
import uz.payday.app.data.local.datastore.TokenManager
import uz.payday.app.data.local.entity.DashboardStatsEntity
import uz.payday.app.data.remote.ApiService
import uz.payday.app.data.remote.dto.LoginRequest
import uz.payday.app.domain.model.DashboardStats
import uz.payday.app.domain.model.Resource
import uz.payday.app.domain.model.User
import uz.payday.app.domain.repository.AuthRepository
import uz.payday.app.domain.repository.DashboardRepository
import javax.inject.Inject
import javax.inject.Singleton

@Singleton
class AuthRepositoryImpl @Inject constructor(
    private val apiService: ApiService,
    private val tokenManager: TokenManager
) : AuthRepository {

    override suspend fun login(email: String, password: String): Resource<User> {
        return try {
            val response = apiService.login(LoginRequest(email, password))
            if (response.isSuccessful && response.body()?.success == true) {
                val data = response.body()!!.data!!
                tokenManager.saveAuthSession(
                    token = data.token,
                    id = data.user.id,
                    name = data.user.name,
                    email = data.user.email,
                    phone = data.user.phone,
                    roles = data.user.roles ?: emptyList()
                )
                Resource.Success(
                    User(
                        id = data.user.id,
                        name = data.user.name,
                        email = data.user.email,
                        phone = data.user.phone,
                        roles = data.user.roles ?: emptyList()
                    )
                )
            } else {
                val errorMsg = response.body()?.message ?: "Login failed. Please check your credentials."
                Resource.Error(errorMsg)
            }
        } catch (e: Exception) {
            Resource.Error(e.localizedMessage ?: "Network connection error")
        }
    }

    override suspend fun getMe(): Resource<User> {
        return try {
            val response = apiService.getMe()
            if (response.isSuccessful && response.body()?.success == true) {
                val userDto = response.body()!!.data!!
                Resource.Success(
                    User(
                        id = userDto.id,
                        name = userDto.name,
                        email = userDto.email,
                        phone = userDto.phone,
                        roles = userDto.roles ?: emptyList()
                    )
                )
            } else {
                Resource.Error(response.body()?.message ?: "Failed to fetch user profile")
            }
        } catch (e: Exception) {
            Resource.Error(e.localizedMessage ?: "Network error")
        }
    }

    override suspend fun logout(): Resource<Unit> {
        return try {
            apiService.logout()
            tokenManager.clearSession()
            Resource.Success(Unit)
        } catch (e: Exception) {
            tokenManager.clearSession()
            Resource.Success(Unit)
        }
    }

    override suspend fun updateProfile(name: String, email: String, phone: String?): Resource<User> {
        return try {
            val body = mutableMapOf("name" to name, "email" to email)
            if (phone != null) body["phone"] = phone
            val response = apiService.updateProfile(body)
            if (response.isSuccessful && response.body()?.success == true) {
                val dto = response.body()!!.data!!
                Resource.Success(User(dto.id, dto.name, dto.email, dto.phone, dto.roles ?: emptyList()))
            } else {
                Resource.Error(response.body()?.message ?: "Update failed")
            }
        } catch (e: Exception) {
            Resource.Error(e.localizedMessage ?: "Network error")
        }
    }

    override suspend fun changePassword(currentPassword: String, newPassword: String): Resource<Unit> {
        return try {
            val body = mapOf(
                "current_password" to currentPassword,
                "new_password" to newPassword,
                "new_password_confirmation" to newPassword
            )
            val response = apiService.changePassword(body)
            if (response.isSuccessful && response.body()?.success == true) {
                Resource.Success(Unit)
            } else {
                Resource.Error(response.body()?.message ?: "Failed to change password")
            }
        } catch (e: Exception) {
            Resource.Error(e.localizedMessage ?: "Network error")
        }
    }

    override fun getAuthToken(): Flow<String?> = tokenManager.tokenFlow
    override fun getLanguage(): Flow<String> = tokenManager.languageFlow
    override suspend fun setLanguage(language: String) = tokenManager.setLanguage(language)
    override fun getThemeMode(): Flow<String> = tokenManager.themeModeFlow
    override suspend fun setThemeMode(mode: String) = tokenManager.setThemeMode(mode)
    override fun isBiometricEnabled(): Flow<Boolean> = tokenManager.biometricEnabledFlow
    override suspend fun setBiometricEnabled(enabled: Boolean) = tokenManager.setBiometricEnabled(enabled)
    override fun getPinCode(): Flow<String?> = tokenManager.pinCodeFlow
    override suspend fun setPinCode(pin: String?) = tokenManager.setPinCode(pin)
    override fun getBaseUrl(): Flow<String> = tokenManager.baseUrlFlow
    override suspend fun setBaseUrl(url: String) = tokenManager.setBaseUrl(url)
}

@Singleton
class DashboardRepositoryImpl @Inject constructor(
    private val apiService: ApiService,
    private val dashboardDao: DashboardDao
) : DashboardRepository {

    override suspend fun getDashboardStats(
        date: String?,
        month: String?,
        firmId: Int?,
        branchId: Int?,
        forceRefresh: Boolean
    ): Resource<DashboardStats> {
        return try {
            val response = apiService.getDashboard(date, month, firmId, branchId)
            if (response.isSuccessful && response.body()?.success == true) {
                val statsDto = response.body()!!.data!!.stats
                val stats = DashboardStats(
                    totalWorkers = statsDto.totalWorkers,
                    inTime = statsDto.inTime,
                    late = statsDto.late,
                    notCome = statsDto.notCome,
                    totalFirms = statsDto.totalFirms,
                    totalBranches = statsDto.totalBranches,
                    date = statsDto.date,
                    month = statsDto.month
                )
                // Cache to Room
                dashboardDao.insertStats(
                    DashboardStatsEntity(
                        totalWorkers = stats.totalWorkers,
                        inTime = stats.inTime,
                        late = stats.late,
                        notCome = stats.notCome,
                        totalFirms = stats.totalFirms,
                        totalBranches = stats.totalBranches,
                        date = stats.date,
                        month = stats.month
                    )
                )
                Resource.Success(stats)
            } else {
                Resource.Error(response.body()?.message ?: "Failed to fetch dashboard")
            }
        } catch (e: Exception) {
            Resource.Error(e.localizedMessage ?: "Offline or network error")
        }
    }
}
