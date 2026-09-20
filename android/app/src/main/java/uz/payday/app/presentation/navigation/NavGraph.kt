package uz.payday.app.presentation.navigation

import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Scaffold
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.ui.Modifier
import androidx.navigation.NavHostController
import androidx.navigation.NavType
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.compose.currentBackStackEntryAsState
import androidx.navigation.navArgument
import uz.payday.app.presentation.attendance.AttendanceGridScreen
import uz.payday.app.presentation.attendance.DailyAttendanceScreen
import uz.payday.app.presentation.auth.LoginScreen
import uz.payday.app.presentation.branches.FirmsAndBranchesScreen
import uz.payday.app.presentation.dashboard.DashboardScreen
import uz.payday.app.presentation.salary.SalaryPaymentsScreen
import uz.payday.app.presentation.salary.SalaryReportScreen
import uz.payday.app.presentation.settings.ChangePasswordScreen
import uz.payday.app.presentation.settings.ProfileScreen
import uz.payday.app.presentation.settings.SettingsScreen
import uz.payday.app.presentation.workers.AddEditWorkerScreen
import uz.payday.app.presentation.workers.WorkerDetailScreen
import uz.payday.app.presentation.workers.WorkerListScreen

@Composable
fun MainNavGraph(
    navController: NavHostController,
    startDestination: String = Screen.Login.route
) {
    val navBackStackEntry by navController.currentBackStackEntryAsState()
    val currentRoute = navBackStackEntry?.destination?.route

    val isBottomBarVisible = currentRoute in listOf(
        Screen.Dashboard.route,
        Screen.Workers.route,
        Screen.Attendance.route,
        Screen.Salary.route,
        Screen.Settings.route
    )

    Scaffold(
        bottomBar = {
            if (isBottomBarVisible) {
                PaydayBottomBar(navController = navController)
            }
        }
    ) { paddingValues ->
        NavHost(
            navController = navController,
            startDestination = startDestination,
            modifier = Modifier.padding(paddingValues)
        ) {
            // Auth
            composable(Screen.Login.route) {
                LoginScreen(
                    onLoginSuccess = {
                        navController.navigate(Screen.Dashboard.route) {
                            popUpTo(Screen.Login.route) { inclusive = true }
                        }
                    }
                )
            }

            // Dashboard
            composable(Screen.Dashboard.route) {
                DashboardScreen(navController = navController)
            }

            // Workers
            composable(Screen.Workers.route) {
                WorkerListScreen(navController = navController)
            }
            composable(
                route = Screen.WorkerDetail.route,
                arguments = listOf(navArgument("workerId") { type = NavType.IntType })
            ) { backStackEntry ->
                val workerId = backStackEntry.arguments?.getInt("workerId") ?: 0
                WorkerDetailScreen(workerId = workerId, navController = navController)
            }
            composable(
                route = Screen.AddEditWorker.route,
                arguments = listOf(
                    navArgument("workerId") {
                        type = NavType.StringType
                        nullable = true
                        defaultValue = null
                    }
                )
            ) { backStackEntry ->
                val workerIdStr = backStackEntry.arguments?.getString("workerId")
                val workerId = workerIdStr?.toIntOrNull()
                AddEditWorkerScreen(workerId = workerId, navController = navController)
            }

            // Attendance
            composable(Screen.Attendance.route) {
                DailyAttendanceScreen(navController = navController)
            }
            composable(Screen.AttendanceGrid.route) {
                AttendanceGridScreen(navController = navController)
            }

            // Salary
            composable(Screen.Salary.route) {
                SalaryReportScreen(navController = navController)
            }
            composable(Screen.SalaryPayments.route) {
                SalaryPaymentsScreen(navController = navController)
            }

            // Branches & Devices
            composable(Screen.FirmsAndBranches.route) {
                FirmsAndBranchesScreen(navController = navController)
            }

            // Settings & Profile
            composable(Screen.Settings.route) {
                SettingsScreen(navController = navController)
            }
            composable(Screen.Profile.route) {
                ProfileScreen(navController = navController)
            }
            composable(Screen.ChangePassword.route) {
                ChangePasswordScreen(navController = navController)
            }
        }
    }
}
