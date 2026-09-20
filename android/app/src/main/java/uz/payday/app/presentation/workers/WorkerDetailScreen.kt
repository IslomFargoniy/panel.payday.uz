package uz.payday.app.presentation.workers

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material.icons.outlined.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.hilt.navigation.compose.hiltViewModel
import androidx.navigation.NavController
import uz.payday.app.domain.model.AccessEvent
import uz.payday.app.presentation.components.AttendanceBadge
import uz.payday.app.presentation.components.PaydayTopBar
import uz.payday.app.presentation.components.WorkerAvatar
import uz.payday.app.presentation.navigation.Screen
import uz.payday.app.util.DateUtils
import uz.payday.app.presentation.theme.*
import uz.payday.app.util.CurrencyFormatter

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun WorkerDetailScreen(
    workerId: Int,
    navController: NavController,
    viewModel: WorkerViewModel = hiltViewModel()
) {
    val uiState by viewModel.detailState.collectAsState()
    var showDeleteConfirmDialog by remember { mutableStateOf(false) }

    LaunchedEffect(workerId) {
        viewModel.loadWorkerDetail(workerId)
    }

    LaunchedEffect(uiState.isDeleted) {
        if (uiState.isDeleted) {
            navController.popBackStack()
        }
    }

    if (showDeleteConfirmDialog) {
        AlertDialog(
            onDismissRequest = { showDeleteConfirmDialog = false },
            title = { Text("Xodimni o'chirish") },
            text = { Text("Haqiqatan ham bu xodimni o'chirib tashlamoqchimisiz?") },
            confirmButton = {
                TextButton(
                    onClick = {
                        showDeleteConfirmDialog = false
                        viewModel.deleteWorker(workerId)
                    },
                    colors = ButtonDefaults.textButtonColors(contentColor = Rose600)
                ) {
                    Text("O'chirish")
                }
            },
            dismissButton = {
                TextButton(onClick = { showDeleteConfirmDialog = false }) {
                    Text("Bekor qilish")
                }
            }
        )
    }

    Scaffold(
        topBar = {
            PaydayTopBar(
                title = uiState.worker?.name ?: "Xodim tafsilotlari",
                navigationIcon = Icons.Default.ArrowBack,
                onNavigationClick = { navController.popBackStack() },
                actions = {
                    IconButton(onClick = {
                        navController.navigate(Screen.AddEditWorker.createRoute(workerId))
                    }) {
                        Icon(Icons.Default.Edit, contentDescription = "Tahrirlash")
                    }
                    IconButton(onClick = { showDeleteConfirmDialog = true }) {
                        Icon(Icons.Default.Delete, contentDescription = "O'chirish", tint = Rose600)
                    }
                }
            )
        },
        containerColor = MaterialTheme.colorScheme.background
    ) { paddingValues ->
        if (uiState.isLoading) {
            Box(
                modifier = Modifier
                    .fillMaxSize()
                    .padding(paddingValues),
                contentAlignment = Alignment.Center
            ) {
                CircularProgressIndicator(color = Indigo600)
            }
        } else if (uiState.worker != null) {
            val worker = uiState.worker!!
            LazyColumn(
                modifier = Modifier
                    .fillMaxSize()
                    .padding(paddingValues),
                contentPadding = PaddingValues(16.dp),
                verticalArrangement = Arrangement.spacedBy(16.dp)
            ) {
                // Profile Header Card
                item {
                    Card(
                        modifier = Modifier.fillMaxWidth(),
                        shape = RoundedCornerShape(20.dp),
                        colors = CardDefaults.cardColors(
                            containerColor = MaterialTheme.colorScheme.surface
                        )
                    ) {
                        Column(
                            modifier = Modifier
                                .fillMaxWidth()
                                .padding(20.dp),
                            horizontalAlignment = Alignment.CenterHorizontally
                        ) {
                            WorkerAvatar(
                                avatarUrl = worker.avatar,
                                name = worker.name,
                                sizeDp = 80
                            )
                            Spacer(modifier = Modifier.height(12.dp))
                            Text(
                                text = worker.name,
                                style = MaterialTheme.typography.headlineSmall.copy(fontWeight = FontWeight.Bold),
                                color = MaterialTheme.colorScheme.onSurface
                            )
                            Spacer(modifier = Modifier.height(4.dp))
                            Text(
                                text = worker.branch?.name ?: "Filialsiz",
                                style = MaterialTheme.typography.bodyMedium,
                                color = Indigo600
                            )
                            if (!worker.phone.isNullOrBlank()) {
                                Spacer(modifier = Modifier.height(2.dp))
                                Text(
                                    text = worker.phone!!,
                                    style = MaterialTheme.typography.bodyMedium,
                                    color = MaterialTheme.colorScheme.onSurfaceVariant
                                )
                            }
                        }
                    }
                }

                // Rates & Schedule Details
                item {
                    Card(
                        modifier = Modifier.fillMaxWidth(),
                        shape = RoundedCornerShape(16.dp),
                        colors = CardDefaults.cardColors(
                            containerColor = MaterialTheme.colorScheme.surface
                        )
                    ) {
                        Column(
                            modifier = Modifier
                                .fillMaxWidth()
                                .padding(16.dp),
                            verticalArrangement = Arrangement.spacedBy(12.dp)
                        ) {
                            Text(
                                text = "Ish rejimi va Stavka",
                                style = MaterialTheme.typography.titleMedium.copy(fontWeight = FontWeight.Bold)
                            )
                            Divider(color = Slate100)

                            DetailRow("Ish vaqti:", "${worker.workTime ?: "-"} — ${worker.endTime ?: "-"}")
                            DetailRow("Soatlik narxi:", CurrencyFormatter.formatUzs(worker.hourPrice))
                            DetailRow("Jarima narxi:", CurrencyFormatter.formatUzs(worker.finePrice))
                            DetailRow("Hisob turi:", if (worker.salaryType == "day") "Kunbay" else "Soatbay")
                            if (!worker.comment.isNullOrBlank()) {
                                DetailRow("Izoh:", worker.comment!!)
                            }
                        }
                    }
                }

                // Monthly Statistics Card
                item {
                    Card(
                        modifier = Modifier.fillMaxWidth(),
                        shape = RoundedCornerShape(16.dp),
                        colors = CardDefaults.cardColors(
                            containerColor = MaterialTheme.colorScheme.surface
                        )
                    ) {
                        Column(
                            modifier = Modifier
                                .fillMaxWidth()
                                .padding(16.dp),
                            verticalArrangement = Arrangement.spacedBy(12.dp)
                        ) {
                            Text(
                                text = "Oylik statistika",
                                style = MaterialTheme.typography.titleMedium.copy(fontWeight = FontWeight.Bold)
                            )
                            Divider(color = Slate100)

                            DetailRow("Ishlagan vaqti:", DateUtils.formatMinutesToHours(worker.workedMinutes))
                            DetailRow("Kechikish vaqti:", "${worker.lateMinutes ?: 0} minut")
                            DetailRow("Kelmagan kunlari:", "${worker.lateDays ?: 0} kun")
                            DetailRow("Ishlagan kunlari:", "${worker.workedDays ?: 0} / ${worker.workDays ?: 0} kun")
                        }
                    }
                }

                // Hikvision Access Logs Section
                item {
                    Text(
                        text = "Oxirgi Kirish-Chiqish Qaydnomalari (Hikvision)",
                        style = MaterialTheme.typography.titleMedium.copy(fontWeight = FontWeight.Bold),
                        color = MaterialTheme.colorScheme.onSurface
                    )
                }

                if (worker.accessEvents.isEmpty()) {
                    item {
                        Card(
                            modifier = Modifier.fillMaxWidth(),
                            shape = RoundedCornerShape(12.dp),
                            colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface)
                        ) {
                            Text(
                                text = "Qaydnomalar mavjud emas",
                                modifier = Modifier.padding(16.dp),
                                style = MaterialTheme.typography.bodyMedium,
                                color = MaterialTheme.colorScheme.onSurfaceVariant
                            )
                        }
                    }
                } else {
                    items(worker.accessEvents) { event ->
                        AccessEventItem(event)
                    }
                }
            }
        }
    }
}

@Composable
fun DetailRow(label: String, value: String) {
    Row(
        modifier = Modifier.fillMaxWidth(),
        horizontalArrangement = Arrangement.SpaceBetween,
        verticalAlignment = Alignment.CenterVertically
    ) {
        Text(
            text = label,
            style = MaterialTheme.typography.bodyMedium,
            color = MaterialTheme.colorScheme.onSurfaceVariant
        )
        Text(
            text = value,
            style = MaterialTheme.typography.bodyMedium.copy(fontWeight = FontWeight.SemiBold),
            color = MaterialTheme.colorScheme.onSurface
        )
    }
}

@Composable
fun AccessEventItem(event: AccessEvent) {
    Card(
        modifier = Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(12.dp),
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface)
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(12.dp),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.SpaceBetween
        ) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                val isCheckIn = event.attendanceStatus == "checkIn"
                Box(
                    modifier = Modifier
                        .size(36.dp)
                        .clip(RoundedCornerShape(8.dp))
                        .background(if (isCheckIn) Emerald50 else Amber50),
                    contentAlignment = Alignment.Center
                ) {
                    Icon(
                        imageVector = if (isCheckIn) Icons.Default.Login else Icons.Default.Logout,
                        contentDescription = null,
                        tint = if (isCheckIn) Emerald600 else Amber600,
                        modifier = Modifier.size(20.dp)
                    )
                }
                Spacer(modifier = Modifier.width(12.dp))
                Column {
                    Text(
                        text = if (isCheckIn) "Kirish (Check In)" else "Chiqish (Check Out)",
                        style = MaterialTheme.typography.titleMedium.copy(fontWeight = FontWeight.SemiBold)
                    )
                    Text(
                        text = DateUtils.formatDisplayDateTime(event.createdAt),
                        style = MaterialTheme.typography.labelSmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant
                    )
                }
            }
            if (!event.workTime.isNullOrBlank()) {
                Text(
                    text = event.workTime!!,
                    style = MaterialTheme.typography.labelMedium.copy(fontWeight = FontWeight.Bold),
                    color = Indigo600
                )
            }
        }
    }
}
