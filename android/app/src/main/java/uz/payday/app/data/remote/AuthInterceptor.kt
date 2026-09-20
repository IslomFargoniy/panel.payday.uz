package uz.payday.app.data.remote

import kotlinx.coroutines.flow.firstOrNull
import kotlinx.coroutines.runBlocking
import okhttp3.Interceptor
import okhttp3.Response
import uz.payday.app.data.local.datastore.TokenManager
import javax.inject.Inject
import javax.inject.Singleton

@Singleton
class AuthInterceptor @Inject constructor(
    private val tokenManager: TokenManager
) : Interceptor {

    override fun intercept(chain: Interceptor.Chain): Response {
        val originalRequest = chain.request()
        val token = runBlocking {
            tokenManager.tokenFlow.firstOrNull()
        }

        val requestBuilder = originalRequest.newBuilder()
            .header("Accept", "application/json")
            .header("Content-Type", "application/json")

        if (!token.isNullOrBlank()) {
            requestBuilder.header("Authorization", "Bearer $token")
        }

        return chain.proceed(requestBuilder.build())
    }
}
