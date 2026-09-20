package uz.payday.app.presentation.navigation

import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material.icons.outlined.*
import androidx.compose.material3.*
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.navigation.NavController
import androidx.navigation.NavGraph.Companion.findStartDestination
import androidx.navigation.compose.currentBackStackEntryAsState
import uz.payday.app.presentation.theme.Indigo600

sealed class Screen(val route: String, val title: String = "", val icon: ImageVector? = null) {
    object Splash : Screen("splash")
    object Login : Screen("login")
    object PinLock : Screen("pin_lock")

    // Bottom Bar Tabs
    object Dashboard : Screen("dashboard", "Dashboard", Icons.Outlined.Dashboard)
    object Workers : Screen("workers", "Xodimlar", Icons.Outlined.People)
    object Attendance : Screen("attendance", "Davomat", Icons.Outlined.CalendarMonth)
    object Salary : Screen("salary", "Oyliklar", Icons.Outlined.Payments)
    object Settings : Screen("settings", "Sozlamalar", Icons.Outlined.Settings)

    // Sub Screens
    object WorkerDetail : Screen("worker_detail/{workerId}") {
        fun createRoute(workerId: Int) = "worker_detail/$workerId"
    }
    object AddEditWorker : Screen("add_edit_worker?workerId={workerId}") {
        fun createRoute(workerId: Int? = null) = if (workerId != null) "add_edit_worker?workerId=$workerId" else "add_edit_worker"
    }
    object MonthlyAttendance : Screen("monthly_attendance")
    object AttendanceGrid : Screen("attendance_grid")
    object SalaryPayments : Screen("salary_payments")
    object FirmsAndBranches : Screen("firms_and_branches", "Filiallar", Icons.Outlined.Business)
    object Profile : Screen("profile", "Profil")
    object ChangePassword : Screen("change_password", "Parolni o'zgartirish")
}

val bottomNavItems = listOf(
    Screen.Dashboard,
    Screen.Workers,
    Screen.Attendance,
    Screen.Salary,
    Screen.Settings
)

@Composable
fun PaydayBottomBar(navController: NavController) {
    val navBackStackEntry by navController.currentBackStackEntryAsState()
    val currentRoute = navBackStackEntry?.destination?.route

    NavigationBar(
        containerColor = MaterialTheme.colorScheme.surface,
        tonalElevation = NavigationBarDefaults.Elevation
    ) {
        bottomNavItems.forEach { screen ->
            val selected = currentRoute == screen.route
            NavigationBarItem(
                icon = {
                    Icon(
                        imageVector = screen.icon ?: Icons.Default.Circle,
                        contentDescription = screen.title
                    )
                },
                label = { Text(screen.title) },
                selected = selected,
                onClick = {
                    if (currentRoute != screen.route) {
                        navController.navigate(screen.route) {
                            popUpTo(navController.graph.findStartDestination().id) {
                                saveState = true
                            }
                            launchSingleTop = true
                            restoreState = true
                        }
                    }
                },
                colors = NavigationBarItemDefaults.colors(
                    selectedIconColor = Indigo600,
                    selectedTextColor = Indigo600,
                    indicatorColor = MaterialTheme.colorScheme.primaryContainer
                )
            )
        }
    }
}
