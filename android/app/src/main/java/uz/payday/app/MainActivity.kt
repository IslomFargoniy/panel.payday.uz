package uz.payday.app

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.compose.foundation.isSystemInDarkTheme
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Modifier
import androidx.navigation.compose.rememberNavController
import dagger.hilt.android.AndroidEntryPoint
import uz.payday.app.data.local.datastore.TokenManager
import uz.payday.app.presentation.navigation.MainNavGraph
import uz.payday.app.presentation.navigation.Screen
import uz.payday.app.presentation.theme.PaydayTheme
import javax.inject.Inject

@AndroidEntryPoint
class MainActivity : ComponentActivity() {

    @Inject
    lateinit var tokenManager: TokenManager

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContent {
            val themeMode by tokenManager.themeModeFlow.collectAsState(initial = "system")
            val token by tokenManager.tokenFlow.collectAsState(initial = null)

            val isDark = when (themeMode) {
                "dark" -> true
                "light" -> false
                else -> isSystemInDarkTheme()
            }

            PaydayTheme(darkTheme = isDark) {
                Surface(
                    modifier = Modifier.fillMaxSize(),
                    color = MaterialTheme.colorScheme.background
                ) {
                    val navController = rememberNavController()
                    val startDestination = if (!token.isNullOrBlank()) Screen.Dashboard.route else Screen.Login.route
                    MainNavGraph(navController = navController, startDestination = startDestination)
                }
            }
        }
    }
}
