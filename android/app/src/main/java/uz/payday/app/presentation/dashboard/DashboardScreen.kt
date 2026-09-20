package uz.payday.app.presentation.dashboard

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
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
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.hilt.navigation.compose.hiltViewModel
import androidx.navigation.NavController
import uz.payday.app.presentation.components.PaydayTopBar
import uz.payday.app.presentation.components.StatCard
import uz.payday.app.presentation.navigation.Screen
import uz.payday.app.presentation.theme.*
import uz.payday.app.util.DateUtils

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun DashboardScreen(
    navController: NavController,
    viewModel: DashboardViewModel = hiltViewModel()
) {
    val uiState by viewModel.uiState.collectAsState()

    Scaffold(
        topBar = {
            PaydayTopBar(
                title = "Dashboard",
                subtitle = "Bugun: ${DateUtils.formatDisplayDate(uiState.selectedDate)}",
                actions = {
                    IconButton(onClick = { viewModel.loadData(forceRefresh = true) }) {
                        Icon(Icons.Default.Refresh, contentDescription = "Refresh")
                    }
                }
            )
        },
        containerColor = MaterialTheme.colorScheme.background
    ) { paddingValues ->
        LazyColumn(
            modifier = Modifier
                .fillMaxSize()
                .padding(paddingValues),
            contentPadding = PaddingValues(16.dp),
            verticalArrangement = Arrangement.spacedBy(16.dp)
        ) {
            // Horizontal Branch / Filter Chips
            item {
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .horizontalScroll(rememberScrollState()),
                    horizontalArrangement = Arrangement.spacedBy(8.dp)
                ) {
                    FilterChip(
                        selected = uiState.selectedBranchId == null,
                        onClick = { viewModel.onBranchSelected(null) },
                        label = { Text("Barcha filiallar") },
                        leadingIcon = {
                            if (uiState.selectedBranchId == null) {
                                Icon(Icons.Default.Check, contentDescription = null, modifier = Modifier.size(16.dp))
                            }
                        }
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
            }

            // Quick Overview Summary Banner
            item {
                Card(
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(20.dp),
                    colors = CardDefaults.cardColors(
                        containerColor = Indigo600
                    )
                ) {
                    Column(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(20.dp)
                    ) {
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.SpaceBetween,
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Column {
                                Text(
                                    text = "Bugungi Davomat Holati",
                                    style = MaterialTheme.typography.titleMedium,
                                    color = Color.White.copy(alpha = 0.9f)
                                )
                                Spacer(modifier = Modifier.height(4.dp))
                                Text(
                                    text = "${uiState.stats.inTime + uiState.stats.late} / ${uiState.stats.totalWorkers} kelgan",
                                    style = MaterialTheme.typography.headlineSmall.copy(fontWeight = FontWeight.Bold),
                                    color = Color.White
                                )
                            }
                            val percentage = if (uiState.stats.totalWorkers > 0) {
                                ((uiState.stats.inTime + uiState.stats.late).toFloat() / uiState.stats.totalWorkers * 100).toInt()
                            } else 0
                            Surface(
                                shape = RoundedCornerShape(12.dp),
                                color = Color.White.copy(alpha = 0.2f)
                            ) {
                                Text(
                                    text = "$percentage%",
                                    modifier = Modifier.padding(horizontal = 12.dp, vertical = 6.dp),
                                    style = MaterialTheme.typography.titleMedium.copy(
                                        fontWeight = FontWeight.Bold,
                                        color = Color.White
                                    )
                                )
                            }
                        }
                    }
                }
            }

            // KPI Grid
            item {
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.spacedBy(12.dp)
                ) {
                    StatCard(
                        title = "Jami xodimlar",
                        value = "${uiState.stats.totalWorkers}",
                        icon = Icons.Default.People,
                        iconBgColor = Indigo50,
                        iconTint = Indigo600,
                        modifier = Modifier.weight(1f)
                    )
                    StatCard(
                        title = "Vaqtida kelgan",
                        value = "${uiState.stats.inTime}",
                        icon = Icons.Default.CheckCircle,
                        iconBgColor = Emerald50,
                        iconTint = Emerald600,
                        modifier = Modifier.weight(1f)
                    )
                }
            }

            item {
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.spacedBy(12.dp)
                ) {
                    StatCard(
                        title = "Kechikkan",
                        value = "${uiState.stats.late}",
                        icon = Icons.Default.Schedule,
                        iconBgColor = Amber50,
                        iconTint = Amber600,
                        modifier = Modifier.weight(1f)
                    )
                    StatCard(
                        title = "Kelmagan",
                        value = "${uiState.stats.notCome}",
                        icon = Icons.Default.Cancel,
                        iconBgColor = Rose50,
                        iconTint = Rose600,
                        modifier = Modifier.weight(1f)
                    )
                }
            }

            // Quick Actions Title
            item {
                Text(
                    text = "Tezkor Amallar",
                    style = MaterialTheme.typography.titleMedium.copy(fontWeight = FontWeight.Bold),
                    color = MaterialTheme.colorScheme.onSurface
                )
            }

            // Quick Navigation Action Cards
            item {
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.spacedBy(12.dp)
                ) {
                    DashboardQuickActionButton(
                        title = "Kunlik davomat",
                        icon = Icons.Outlined.Today,
                        color = Indigo600,
                        bgColor = Indigo50,
                        modifier = Modifier.weight(1f),
                        onClick = { navController.navigate(Screen.Attendance.route) }
                    )
                    DashboardQuickActionButton(
                        title = "Oylik jadval (31 kun)",
                        icon = Icons.Outlined.CalendarViewMonth,
                        color = Emerald600,
                        bgColor = Emerald50,
                        modifier = Modifier.weight(1f),
                        onClick = { navController.navigate(Screen.AttendanceGrid.route) }
                    )
                }
            }

            item {
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.spacedBy(12.dp)
                ) {
                    DashboardQuickActionButton(
                        title = "Oylik hisoblash",
                        icon = Icons.Outlined.AttachMoney,
                        color = Amber600,
                        bgColor = Amber50,
                        modifier = Modifier.weight(1f),
                        onClick = { navController.navigate(Screen.Salary.route) }
                    )
                    DashboardQuickActionButton(
                        title = "Filiallar & Qurilmalar",
                        icon = Icons.Outlined.Store,
                        color = Slate700,
                        bgColor = Slate100,
                        modifier = Modifier.weight(1f),
                        onClick = { navController.navigate(Screen.FirmsAndBranches.route) }
                    )
                }
            }
        }
    }
}

@Composable
fun DashboardQuickActionButton(
    title: String,
    icon: ImageVector,
    color: Color,
    bgColor: Color,
    onClick: () -> Unit,
    modifier: Modifier = Modifier
) {
    Card(
        modifier = modifier.clickable { onClick() },
        shape = RoundedCornerShape(16.dp),
        colors = CardDefaults.cardColors(
            containerColor = MaterialTheme.colorScheme.surface
        ),
        elevation = CardDefaults.cardElevation(defaultElevation = 1.dp)
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(16.dp),
            verticalArrangement = Arrangement.Center
        ) {
            Box(
                modifier = Modifier
                    .size(40.dp)
                    .clip(RoundedCornerShape(10.dp))
                    .background(bgColor),
                contentAlignment = Alignment.Center
            ) {
                Icon(
                    imageVector = icon,
                    contentDescription = null,
                    tint = color,
                    modifier = Modifier.size(22.dp)
                )
            }
            Spacer(modifier = Modifier.height(12.dp))
            Text(
                text = title,
                style = MaterialTheme.typography.titleMedium.copy(fontWeight = FontWeight.SemiBold),
                color = MaterialTheme.colorScheme.onSurface
            )
        }
    }
}
