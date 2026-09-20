package uz.payday.app.presentation.workers

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
import uz.payday.app.domain.model.Worker
import uz.payday.app.presentation.components.EmptyState
import uz.payday.app.presentation.components.ExcelExportButton
import uz.payday.app.presentation.components.PaydayTopBar
import uz.payday.app.presentation.components.WorkerAvatar
import uz.payday.app.presentation.navigation.Screen
import uz.payday.app.presentation.theme.*
import uz.payday.app.util.CurrencyFormatter
import uz.payday.app.util.DateUtils

@Composable
fun WorkerListScreen(
    navController: NavController,
    viewModel: WorkerViewModel = hiltViewModel()
) {
    val uiState by viewModel.listState.collectAsState()
    val context = LocalContext.current

    Scaffold(
        topBar = {
            PaydayTopBar(
                title = "Xodimlar",
                subtitle = "Jami: ${uiState.workers.size} ta xodim",
                actions = {
                    IconButton(onClick = { viewModel.loadWorkers() }) {
                        Icon(Icons.Default.Refresh, contentDescription = "Yangilash")
                    }
                }
            )
        },
        floatingActionButton = {
            FloatingActionButton(
                onClick = { navController.navigate(Screen.AddEditWorker.createRoute()) },
                containerColor = Indigo600,
                contentColor = MaterialTheme.colorScheme.onPrimary
            ) {
                Icon(Icons.Default.Add, contentDescription = "Xodim qo'shish")
            }
        },
        containerColor = MaterialTheme.colorScheme.background
    ) { paddingValues ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(paddingValues)
        ) {
            // Search Input
            OutlinedTextField(
                value = uiState.searchQuery,
                onValueChange = viewModel::onSearchQueryChanged,
                placeholder = { Text("Xodim ismi yoki telefon...") },
                leadingIcon = {
                    Icon(Icons.Default.Search, contentDescription = null, tint = Indigo600)
                },
                trailingIcon = {
                    if (uiState.searchQuery.isNotEmpty()) {
                        IconButton(onClick = { viewModel.onSearchQueryChanged("") }) {
                            Icon(Icons.Default.Clear, contentDescription = "Tozalash")
                        }
                    }
                },
                singleLine = true,
                shape = RoundedCornerShape(12.dp),
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(horizontal = 16.dp, vertical = 8.dp)
            )

            // Filter Row: Inline Excel Button on Left of Filters!
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .horizontalScroll(rememberScrollState())
                    .padding(horizontal = 16.dp, vertical = 4.dp),
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                // Excel Button directly on left
                ExcelExportButton(
                    onClick = { viewModel.exportToExcel(context) },
                    isLoading = uiState.isExporting
                )

                FilterChip(
                    selected = uiState.selectedBranchId == null,
                    onClick = { viewModel.onBranchFilterChanged(null) },
                    label = { Text("Barchasi") },
                    leadingIcon = {
                        if (uiState.selectedBranchId == null) {
                            Icon(Icons.Default.Check, contentDescription = null, modifier = Modifier.size(16.dp))
                        }
                    }
                )

                uiState.branches.forEach { branch ->
                    FilterChip(
                        selected = uiState.selectedBranchId == branch.id,
                        onClick = { viewModel.onBranchFilterChanged(branch.id) },
                        label = { Text(branch.name) },
                        leadingIcon = {
                            if (uiState.selectedBranchId == branch.id) {
                                Icon(Icons.Default.Check, contentDescription = null, modifier = Modifier.size(16.dp))
                            }
                        }
                    )
                }
            }

            Spacer(modifier = Modifier.height(8.dp))

            if (uiState.isLoading && uiState.workers.isEmpty()) {
                Box(
                    modifier = Modifier.fillMaxSize(),
                    contentAlignment = Alignment.Center
                ) {
                    CircularProgressIndicator(color = Indigo600)
                }
            } else if (uiState.workers.isEmpty()) {
                EmptyState(
                    message = "Xodimlar topilmadi",
                    onRetry = { viewModel.loadWorkers() }
                )
            } else {
                LazyColumn(
                    modifier = Modifier.fillMaxSize(),
                    contentPadding = PaddingValues(horizontal = 16.dp, vertical = 8.dp),
                    verticalArrangement = Arrangement.spacedBy(10.dp)
                ) {
                    items(uiState.workers, key = { it.id }) { worker ->
                        WorkerListItem(
                            worker = worker,
                            onClick = {
                                navController.navigate(Screen.WorkerDetail.createRoute(worker.id))
                            }
                        )
                    }
                }
            }
        }
    }
}

@Composable
fun WorkerListItem(
    worker: Worker,
    onClick: () -> Unit
) {
    Card(
        modifier = Modifier
            .fillMaxWidth()
            .clickable { onClick() },
        shape = RoundedCornerShape(16.dp),
        colors = CardDefaults.cardColors(
            containerColor = MaterialTheme.colorScheme.surface
        ),
        elevation = CardDefaults.cardElevation(defaultElevation = 1.dp)
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(14.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            WorkerAvatar(
                avatarUrl = worker.avatar,
                name = worker.name,
                sizeDp = 48
            )

            Spacer(modifier = Modifier.width(14.dp))

            Column(modifier = Modifier.weight(1f)) {
                Text(
                    text = worker.name,
                    style = MaterialTheme.typography.titleMedium.copy(fontWeight = FontWeight.Bold),
                    color = MaterialTheme.colorScheme.onSurface
                )
                Spacer(modifier = Modifier.height(2.dp))
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Icon(
                        imageVector = Icons.Outlined.Store,
                        contentDescription = null,
                        modifier = Modifier.size(14.dp),
                        tint = MaterialTheme.colorScheme.onSurfaceVariant
                    )
                    Spacer(modifier = Modifier.width(4.dp))
                    Text(
                        text = worker.branch?.name ?: "Filialsiz",
                        style = MaterialTheme.typography.bodyMedium,
                        color = MaterialTheme.colorScheme.onSurfaceVariant
                    )
                }
                Spacer(modifier = Modifier.height(4.dp))
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween
                ) {
                    Text(
                        text = "Stavka: ${CurrencyFormatter.formatUzs(worker.hourPrice)}/s",
                        style = MaterialTheme.typography.labelSmall.copy(fontWeight = FontWeight.SemiBold),
                        color = Indigo600
                    )
                    Text(
                        text = "Kechikish: ${worker.lateMinutes ?: 0} min",
                        style = MaterialTheme.typography.labelSmall,
                        color = if ((worker.lateMinutes ?: 0) > 0) Amber600 else Emerald600
                    )
                }
            }

            Icon(
                imageVector = Icons.Default.ChevronRight,
                contentDescription = null,
                tint = MaterialTheme.colorScheme.onSurfaceVariant
            )
        }
    }
}
