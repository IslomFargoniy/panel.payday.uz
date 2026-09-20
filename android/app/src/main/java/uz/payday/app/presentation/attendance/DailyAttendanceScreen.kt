package uz.payday.app.presentation.attendance

import androidx.compose.foundation.clickable
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material.icons.outlined.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.hilt.navigation.compose.hiltViewModel
import androidx.navigation.NavController
import uz.payday.app.presentation.components.AttendanceBadge
import uz.payday.app.presentation.components.EmptyState
import uz.payday.app.presentation.components.ExcelExportButton
import uz.payday.app.presentation.components.PaydayTopBar
import uz.payday.app.presentation.navigation.Screen
import uz.payday.app.presentation.theme.*
import uz.payday.app.util.DateUtils

@Suppress("UNCHECKED_CAST")
@Composable
fun DailyAttendanceScreen(
    navController: NavController,
    viewModel: AttendanceViewModel = hiltViewModel()
) {
    val uiState by viewModel.uiState.collectAsState()
    val context = LocalContext.current

    val workersList = (uiState.dailyData["workers"] as? List<Map<String, Any>>) ?: emptyList()

    Scaffold(
        topBar = {
            PaydayTopBar(
                title = "Kunlik Davomat",
                subtitle = "Sana: ${DateUtils.formatDisplayDate(uiState.selectedDate)}",
                actions = {
                    IconButton(onClick = {
                        navController.navigate(Screen.AttendanceGrid.route)
                    }) {
                        Icon(Icons.Outlined.CalendarMonth, contentDescription = "31 kunlik jadval")
                    }
                    IconButton(onClick = { viewModel.loadDailyAttendance() }) {
                        Icon(Icons.Default.Refresh, contentDescription = "Yangilash")
                    }
                }
            )
        },
        containerColor = MaterialTheme.colorScheme.background
    ) { paddingValues ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(paddingValues)
        ) {
            // Filter Bar with Excel Button on LEFT
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .horizontalScroll(rememberScrollState())
                    .padding(horizontal = 16.dp, vertical = 8.dp),
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                // Inline Excel Button on Left
                ExcelExportButton(
                    onClick = { viewModel.exportToExcel(context) },
                    isLoading = uiState.isExporting
                )

                uiState.branches.forEach { branch ->
                    FilterChip(
                        selected = uiState.selectedBranchId == branch.id,
                        onClick = { viewModel.onBranchSelected(branch.id) },
                        label = { Text(branch.name) },
                        leadingIcon = {
                            if (uiState.selectedBranchId == branch.id) {
                                Icon(Icons.Default.Check, contentDescription = null, modifier = Modifier.size(16.dp))
                            }
                        }
                    )
                }
            }

            if (uiState.isLoading) {
                Box(
                    modifier = Modifier.fillMaxSize(),
                    contentAlignment = Alignment.Center
                ) {
                    CircularProgressIndicator(color = Indigo600)
                }
            } else if (workersList.isEmpty()) {
                EmptyState(
                    message = "Bugungi kunda davomat qaydlari topilmadi",
                    onRetry = { viewModel.loadDailyAttendance() }
                )
            } else {
                LazyColumn(
                    modifier = Modifier.fillMaxSize(),
                    contentPadding = PaddingValues(horizontal = 16.dp, vertical = 8.dp),
                    verticalArrangement = Arrangement.spacedBy(10.dp)
                ) {
                    items(workersList) { item ->
                        val workerName = item["name"] as? String ?: ""
                        val checkIn = item["check_in"] as? String ?: "-"
                        val checkOut = item["check_out"] as? String ?: "-"
                        val lateDuration = item["late_duration"] as? String ?: "00:00"
                        val workedDuration = item["worked_duration"] as? String ?: "00:00"
                        val status = when {
                            checkIn != "-" && lateDuration == "00:00" -> "in_time"
                            checkIn != "-" -> "late"
                            else -> "not_come"
                        }

                        Card(
                            modifier = Modifier.fillMaxWidth(),
                            shape = RoundedCornerShape(14.dp),
                            colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
                            elevation = CardDefaults.cardElevation(defaultElevation = 1.dp)
                        ) {
                            Column(
                                modifier = Modifier
                                    .fillMaxWidth()
                                    .padding(14.dp)
                            ) {
                                Row(
                                    modifier = Modifier.fillMaxWidth(),
                                    horizontalArrangement = Arrangement.SpaceBetween,
                                    verticalAlignment = Alignment.CenterVertically
                                ) {
                                    Text(
                                        text = workerName,
                                        style = MaterialTheme.typography.titleMedium.copy(fontWeight = FontWeight.Bold),
                                        color = MaterialTheme.colorScheme.onSurface
                                    )
                                    AttendanceBadge(status = status)
                                }

                                Spacer(modifier = Modifier.height(10.dp))
                                Divider(color = Slate100)
                                Spacer(modifier = Modifier.height(10.dp))

                                Row(
                                    modifier = Modifier.fillMaxWidth(),
                                    horizontalArrangement = Arrangement.SpaceBetween
                                ) {
                                    Column {
                                        Text(
                                            text = "Keldi / Ketdi",
                                            style = MaterialTheme.typography.labelSmall,
                                            color = MaterialTheme.colorScheme.onSurfaceVariant
                                        )
                                        Text(
                                            text = "$checkIn — $checkOut",
                                            style = MaterialTheme.typography.bodyMedium.copy(fontWeight = FontWeight.SemiBold),
                                            color = MaterialTheme.colorScheme.onSurface
                                        )
                                    }

                                    Column(horizontalAlignment = Alignment.End) {
                                        Text(
                                            text = "Ishlagan / Kechikish",
                                            style = MaterialTheme.typography.labelSmall,
                                            color = MaterialTheme.colorScheme.onSurfaceVariant
                                        )
                                        Text(
                                            text = "$workedDuration / $lateDuration",
                                            style = MaterialTheme.typography.bodyMedium.copy(fontWeight = FontWeight.SemiBold),
                                            color = if (lateDuration != "00:00") Amber600 else Emerald600
                                        )
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}
