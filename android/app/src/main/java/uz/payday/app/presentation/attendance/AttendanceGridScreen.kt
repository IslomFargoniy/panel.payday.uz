package uz.payday.app.presentation.attendance

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.ArrowBack
import androidx.compose.material.icons.filled.Check
import androidx.compose.material.icons.filled.Refresh
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.hilt.navigation.compose.hiltViewModel
import androidx.navigation.NavController
import uz.payday.app.presentation.components.EmptyState
import uz.payday.app.presentation.components.ExcelExportButton
import uz.payday.app.presentation.components.PaydayTopBar
import uz.payday.app.presentation.theme.*

@Suppress("UNCHECKED_CAST")
@Composable
fun AttendanceGridScreen(
    navController: NavController,
    viewModel: AttendanceViewModel = hiltViewModel()
) {
    val uiState by viewModel.uiState.collectAsState()
    val context = LocalContext.current
    val gridScrollState = rememberScrollState()

    LaunchedEffect(uiState.selectedBranchId, uiState.selectedMonth) {
        viewModel.loadAttendanceGrid()
    }

    val daysInMonth = (uiState.gridData["days_in_month"] as? Number)?.toInt() ?: 31
    val workers = (uiState.gridData["workers"] as? List<Map<String, Any>>) ?: emptyList()

    Scaffold(
        topBar = {
            PaydayTopBar(
                title = "Oylik Jadval (31 kun)",
                subtitle = "Oy: ${uiState.selectedMonth}",
                navigationIcon = Icons.Default.ArrowBack,
                onNavigationClick = { navController.popBackStack() },
                actions = {
                    IconButton(onClick = { viewModel.loadAttendanceGrid() }) {
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
            // Branch Filter Row with Inline Excel Button
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .horizontalScroll(rememberScrollState())
                    .padding(horizontal = 16.dp, vertical = 8.dp),
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(8.dp)
            ) {
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
            } else if (workers.isEmpty()) {
                EmptyState(
                    message = "Davomat jadvali bo'sh",
                    onRetry = { viewModel.loadAttendanceGrid() }
                )
            } else {
                // 2D Scrollable Grid Matrix Table
                Box(
                    modifier = Modifier
                        .fillMaxSize()
                        .padding(horizontal = 16.dp, vertical = 8.dp)
                        .clip(RoundedCornerShape(12.dp))
                        .background(MaterialTheme.colorScheme.surface)
                        .border(1.dp, Slate200, RoundedCornerShape(12.dp))
                ) {
                    Column(
                        modifier = Modifier
                            .fillMaxSize()
                            .horizontalScroll(gridScrollState)
                    ) {
                        // Header Row (Worker Name + Days 1..31)
                        Row(
                            modifier = Modifier
                                .background(Slate100)
                                .padding(vertical = 10.dp, horizontal = 8.dp),
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Text(
                                text = "F.I.SH",
                                modifier = Modifier.width(160.dp),
                                style = MaterialTheme.typography.titleMedium.copy(fontWeight = FontWeight.Bold),
                                color = MaterialTheme.colorScheme.onSurface
                            )
                            for (d in 1..daysInMonth) {
                                Box(
                                    modifier = Modifier.width(38.dp),
                                    contentAlignment = Alignment.Center
                                ) {
                                    Text(
                                        text = "$d",
                                        style = MaterialTheme.typography.labelSmall.copy(fontWeight = FontWeight.Bold),
                                        color = MaterialTheme.colorScheme.onSurface
                                    )
                                }
                            }
                        }

                        Divider(color = Slate200)

                        // Data Rows
                        LazyColumn(
                            modifier = Modifier.fillMaxSize()
                        ) {
                            items(workers) { workerMap ->
                                val name = workerMap["name"] as? String ?: ""
                                val daysData = (workerMap["days"] as? Map<String, Any>) ?: emptyMap()

                                Row(
                                    modifier = Modifier
                                        .fillMaxWidth()
                                        .padding(vertical = 8.dp, horizontal = 8.dp),
                                    verticalAlignment = Alignment.CenterVertically
                                ) {
                                    Text(
                                        text = name,
                                        modifier = Modifier.width(160.dp),
                                        style = MaterialTheme.typography.bodyMedium.copy(fontWeight = FontWeight.SemiBold),
                                        maxLines = 1,
                                        overflow = TextOverflow.Ellipsis
                                    )

                                    for (d in 1..daysInMonth) {
                                        val dayKey = d.toString().padStart(2, '0')
                                        val dayStatus = daysData[dayKey] as? String ?: daysData[d.toString()] as? String

                                        val (bgColor, text, textColor) = when (dayStatus) {
                                            "in_time", "present" -> Triple(Emerald50, "✓", Emerald600)
                                            "late" -> Triple(Amber50, "!", Amber600)
                                            "absent", "not_come" -> Triple(Rose50, "✕", Rose600)
                                            else -> Triple(Color.Transparent, "-", Slate400)
                                        }

                                        Box(
                                            modifier = Modifier
                                                .width(38.dp)
                                                .height(32.dp)
                                                .padding(2.dp)
                                                .clip(RoundedCornerShape(6.dp))
                                                .background(bgColor),
                                            contentAlignment = Alignment.Center
                                        ) {
                                            Text(
                                                text = text,
                                                style = MaterialTheme.typography.labelMedium.copy(
                                                    fontWeight = FontWeight.Bold,
                                                    color = textColor,
                                                    fontSize = 11.sp
                                                )
                                            )
                                        }
                                    }
                                }
                                Divider(color = Slate100)
                            }
                        }
                    }
                }
            }
        }
    }
}
