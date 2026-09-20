package uz.payday.app.di

import android.content.Context
import androidx.room.Room
import com.google.gson.Gson
import com.google.gson.GsonBuilder
import dagger.Binds
import dagger.Module
import dagger.Provides
import dagger.hilt.InstallIn
import dagger.hilt.android.qualifiers.ApplicationContext
import dagger.hilt.components.SingletonComponent
import kotlinx.coroutines.flow.firstOrNull
import kotlinx.coroutines.runBlocking
import okhttp3.OkHttpClient
import okhttp3.logging.HttpLoggingInterceptor
import retrofit2.Retrofit
import retrofit2.converter.gson.GsonConverterFactory
import uz.payday.app.data.local.PaydayDatabase
import uz.payday.app.data.local.dao.DashboardDao
import uz.payday.app.data.local.dao.WorkerDao
import uz.payday.app.data.local.datastore.TokenManager
import uz.payday.app.data.remote.ApiService
import uz.payday.app.data.remote.AuthInterceptor
import uz.payday.app.data.repository.*
import uz.payday.app.domain.repository.*
import java.util.concurrent.TimeUnit
import javax.inject.Singleton

@Module
@InstallIn(SingletonComponent::class)
object NetworkModule {

    @Provides
    @Singleton
    fun provideGson(): Gson = GsonBuilder().setLenient().create()

    @Provides
    @Singleton
    fun provideLoggingInterceptor(): HttpLoggingInterceptor {
        return HttpLoggingInterceptor().apply {
            level = HttpLoggingInterceptor.Level.BODY
        }
    }

    @Provides
    @Singleton
    fun provideOkHttpClient(
        authInterceptor: AuthInterceptor,
        loggingInterceptor: HttpLoggingInterceptor
    ): OkHttpClient {
        return OkHttpClient.Builder()
            .addInterceptor(authInterceptor)
            .addInterceptor(loggingInterceptor)
            .connectTimeout(30, TimeUnit.SECONDS)
            .readTimeout(30, TimeUnit.SECONDS)
            .writeTimeout(30, TimeUnit.SECONDS)
            .build()
    }

    @Provides
    @Singleton
    fun provideApiService(
        okHttpClient: OkHttpClient,
        gson: Gson,
        tokenManager: TokenManager
    ): ApiService {
        val baseUrl = runBlocking {
            tokenManager.baseUrlFlow.firstOrNull() ?: TokenManager.DEFAULT_BASE_URL
        }
        return Retrofit.Builder()
            .baseUrl(baseUrl)
            .client(okHttpClient)
            .addConverterFactory(GsonConverterFactory.create(gson))
            .build()
            .create(ApiService::class.java)
    }
}

@Module
@InstallIn(SingletonComponent::class)
object DatabaseModule {

    @Provides
    @Singleton
    fun provideDatabase(@ApplicationContext context: Context): PaydayDatabase {
        return Room.databaseBuilder(
            context,
            PaydayDatabase::class.java,
            PaydayDatabase.DATABASE_NAME
        ).fallbackToDestructiveMigration().build()
    }

    @Provides
    fun provideWorkerDao(database: PaydayDatabase): WorkerDao = database.workerDao()

    @Provides
    fun provideDashboardDao(database: PaydayDatabase): DashboardDao = database.dashboardDao()
}

@Module
@InstallIn(SingletonComponent::class)
abstract class RepositoryModule {

    @Binds
    @Singleton
    abstract fun bindAuthRepository(impl: AuthRepositoryImpl): AuthRepository

    @Binds
    @Singleton
    abstract fun bindDashboardRepository(impl: DashboardRepositoryImpl): DashboardRepository

    @Binds
    @Singleton
    abstract fun bindWorkerRepository(impl: WorkerRepositoryImpl): WorkerRepository

    @Binds
    @Singleton
    abstract fun bindAttendanceRepository(impl: AttendanceRepositoryImpl): AttendanceRepository

    @Binds
    @Singleton
    abstract fun bindSalaryRepository(impl: SalaryRepositoryImpl): SalaryRepository

    @Binds
    @Singleton
    abstract fun bindFirmRepository(impl: FirmRepositoryImpl): FirmRepository
}
