package uz.payday.app.presentation.salary

import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.ArrowBack
import androidx.compose.material.icons.filled.Refresh
import androidx.compose.material3.*
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.hilt.navigation.compose.hiltViewModel
import androidx.navigation.NavController
import uz.payday.app.presentation.components.EmptyState
import uz.payday.app.presentation.components.PaydayTopBar
import uz.payday.app.presentation.theme.*
import uz.payday.app.util.CurrencyFormatter
import uz.payday.app.util.DateUtils

@Composable
fun SalaryPaymentsScreen(
    navController: NavController,
    viewModel: SalaryViewModel = hiltViewModel()
) {
    val uiState by viewModel.uiState.collectAsState()

    LaunchedEffect(Unit) {
        viewModel.loadSalaryPayments()
    }

    Scaffold(
        topBar = {
            PaydayTopBar(
                title = "To'lovlar Tarixi",
                subtitle = "Jami: ${uiState.payments.size} ta to'lov",
                navigationIcon = Icons.Default.ArrowBack,
                onNavigationClick = { navController.popBackStack() },
                actions = {
                    IconButton(onClick = { viewModel.loadSalaryPayments() }) {
                        Icon(Icons.Default.Refresh, contentDescription = "Yangilash")
                    }
                }
            )
        },
        containerColor = MaterialTheme.colorScheme.background
    ) { paddingValues ->
        if (uiState.payments.isEmpty()) {
            EmptyState(
                message = "Hozircha to'lovlar qayd etilmagan",
                onRetry = { viewModel.loadSalaryPayments() },
                modifier = Modifier.padding(paddingValues)
            )
        } else {
            LazyColumn(
                modifier = Modifier
                    .fillMaxSize()
                    .padding(paddingValues),
                contentPadding = PaddingValues(16.dp),
                verticalArrangement = Arrangement.spacedBy(10.dp)
            ) {
                items(uiState.payments) { payment ->
                    Card(
                        modifier = Modifier.fillMaxWidth(),
                        shape = RoundedCornerShape(14.dp),
                        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
                        elevation = CardDefaults.cardElevation(defaultElevation = 1.dp)
                    ) {
                        Row(
                            modifier = Modifier
                                .fillMaxWidth()
                                .padding(16.dp),
                            horizontalArrangement = Arrangement.SpaceBetween,
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Column {
                                Text(
                                    text = payment.workerName ?: "Xodim",
                                    style = MaterialTheme.typography.titleMedium.copy(fontWeight = FontWeight.Bold)
                                )
                                Spacer(modifier = Modifier.height(2.dp))
                                Text(
                                    text = "Sana: ${DateUtils.formatDisplayDate(payment.date)}",
                                    style = MaterialTheme.typography.bodyMedium,
                                    color = MaterialTheme.colorScheme.onSurfaceVariant
                                )
                                if (!payment.comment.isNullOrBlank()) {
                                    Spacer(modifier = Modifier.height(2.dp))
                                    Text(
                                        text = payment.comment,
                                        style = MaterialTheme.typography.labelSmall,
                                        color = MaterialTheme.colorScheme.onSurfaceVariant
                                    )
                                }
                            }

                            Text(
                                text = "+${CurrencyFormatter.formatUzs(payment.amount)}",
                                style = MaterialTheme.typography.titleMedium.copy(
                                    fontWeight = FontWeight.Bold,
                                    color = Emerald600
                                )
                            )
                        }
                    }
                }
            }
        }
    }
}
