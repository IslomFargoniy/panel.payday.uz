package uz.payday.app.presentation.salary

import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material.icons.outlined.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.dp
import androidx.hilt.navigation.compose.hiltViewModel
import androidx.navigation.NavController
import uz.payday.app.presentation.components.EmptyState
import uz.payday.app.presentation.components.ExcelExportButton
import uz.payday.app.presentation.components.PaydayTopBar
import uz.payday.app.presentation.navigation.Screen
import uz.payday.app.presentation.theme.*
import uz.payday.app.util.CurrencyFormatter
import uz.payday.app.util.DateUtils

@Suppress("UNCHECKED_CAST")
@Composable
fun SalaryReportScreen(
    navController: NavController,
    viewModel: SalaryViewModel = hiltViewModel()
) {
    val uiState by viewModel.uiState.collectAsState()
    val context = LocalContext.current

    var selectedWorkerForPayment by remember { mutableStateOf<Map<String, Any>?>(null) }
    var paymentAmountInput by remember { mutableStateOf("") }
    var paymentCommentInput by remember { mutableStateOf("") }

    val workersList = (uiState.reportData["workers"] as? List<Map<String, Any>>) ?: emptyList()
    val totalAmount = (uiState.reportData["total_amount"] as? Number)?.toDouble() ?: 0.0

    // Payment Dialog
    if (selectedWorkerForPayment != null) {
        val workerName = selectedWorkerForPayment!!["name"] as? String ?: ""
        val workerId = (selectedWorkerForPayment!!["id"] as? Number)?.toInt() ?: 0

        AlertDialog(
            onDismissRequest = { selectedWorkerForPayment = null },
            title = { Text("Ish haqidan to'lov qilish") },
            text = {
                Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
                    Text(
                        text = "Xodim: $workerName",
                        style = MaterialTheme.typography.titleMedium.copy(fontWeight = FontWeight.Bold)
                    )
                    OutlinedTextField(
                        value = paymentAmountInput,
                        onValueChange = { paymentAmountInput = it },
                        label = { Text("To'lov summasi (so'm)") },
                        keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                        singleLine = true,
                        modifier = Modifier.fillMaxWidth()
                    )
                    OutlinedTextField(
                        value = paymentCommentInput,
                        onValueChange = { paymentCommentInput = it },
                        label = { Text("Izoh (ixtiyoriy)") },
                        maxLines = 2,
                        modifier = Modifier.fillMaxWidth()
                    )
                }
            },
            confirmButton = {
                Button(
                    onClick = {
                        val amount = paymentAmountInput.toDoubleOrNull() ?: 0.0
                        if (amount > 0) {
                            viewModel.recordPayment(
                                workerId = workerId,
                                amount = amount,
                                date = DateUtils.today(),
                                comment = paymentCommentInput.ifBlank { null }
                            )
                            selectedWorkerForPayment = null
                            paymentAmountInput = ""
                            paymentCommentInput = ""
                        }
                    },
                    colors = ButtonDefaults.buttonColors(containerColor = Emerald600)
                ) {
                    Text("To'lash")
                }
            },
            dismissButton = {
                TextButton(onClick = { selectedWorkerForPayment = null }) {
                    Text("Bekor qilish")
                }
            }
        )
    }

    Scaffold(
        topBar = {
            PaydayTopBar(
                title = "Oylik Hisoboti",
                subtitle = "Oy: ${uiState.selectedMonth}",
                actions = {
                    IconButton(onClick = { navController.navigate(Screen.SalaryPayments.route) }) {
                        Icon(Icons.Outlined.ReceiptLong, contentDescription = "To'lovlar tarixi")
                    }
                    IconButton(onClick = { viewModel.loadSalaryReport() }) {
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
            // Filter Bar with Inline Excel Button on LEFT
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

                FilterChip(
                    selected = uiState.selectedBranchId == null,
                    onClick = { viewModel.onBranchFilterChanged(null) },
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

            // Summary Card
            Card(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(horizontal = 16.dp, vertical = 4.dp),
                shape = RoundedCornerShape(16.dp),
                colors = CardDefaults.cardColors(containerColor = Indigo600)
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
                            text = "Jami hisoblangan ish haqi",
                            style = MaterialTheme.typography.bodyMedium,
                            color = MaterialTheme.colorScheme.onPrimary.copy(alpha = 0.8f)
                        )
                        Text(
                            text = CurrencyFormatter.formatUzs(totalAmount),
                            style = MaterialTheme.typography.headlineSmall.copy(fontWeight = FontWeight.Bold),
                            color = MaterialTheme.colorScheme.onPrimary
                        )
                    }
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
                    message = "Oylik hisoboti bo'sh",
                    onRetry = { viewModel.loadSalaryReport() }
                )
            } else {
                LazyColumn(
                    modifier = Modifier.fillMaxSize(),
                    contentPadding = PaddingValues(16.dp),
                    verticalArrangement = Arrangement.spacedBy(10.dp)
                ) {
                    items(workersList) { item ->
                        val workerName = item["name"] as? String ?: ""
                        val workedHours = (item["worked_hours"] as? Number)?.toDouble() ?: 0.0
                        val fineHours = (item["fine_hours"] as? Number)?.toDouble() ?: 0.0
                        val hourPrice = (item["hour_price"] as? Number)?.toDouble() ?: 0.0
                        val salary = (item["total_salary"] as? Number)?.toDouble() ?: 0.0
                        val paid = (item["total_paid"] as? Number)?.toDouble() ?: 0.0
                        val remaining = salary - paid

                        Card(
                            modifier = Modifier.fillMaxWidth(),
                            shape = RoundedCornerShape(14.dp),
                            colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
                            elevation = CardDefaults.cardElevation(defaultElevation = 1.dp)
                        ) {
                            Column(
                                modifier = Modifier
                                    .fillMaxWidth()
                                    .padding(14.dp),
                                verticalArrangement = Arrangement.spacedBy(8.dp)
                            ) {
                                Row(
                                    modifier = Modifier.fillMaxWidth(),
                                    horizontalArrangement = Arrangement.SpaceBetween,
                                    verticalAlignment = Alignment.CenterVertically
                                ) {
                                    Text(
                                        text = workerName,
                                        style = MaterialTheme.typography.titleMedium.copy(fontWeight = FontWeight.Bold)
                                    )
                                    Text(
                                        text = CurrencyFormatter.formatUzs(salary),
                                        style = MaterialTheme.typography.titleMedium.copy(
                                            fontWeight = FontWeight.Bold,
                                            color = Indigo600
                                        )
                                    )
                                }

                                Divider(color = Slate100)

                                Row(
                                    modifier = Modifier.fillMaxWidth(),
                                    horizontalArrangement = Arrangement.SpaceBetween
                                ) {
                                    Text(
                                        text = "Ishlagan: ${workedHours}s | Jarima: ${fineHours}s",
                                        style = MaterialTheme.typography.bodyMedium,
                                        color = MaterialTheme.colorScheme.onSurfaceVariant
                                    )
                                    Text(
                                        text = "Qoldiq: ${CurrencyFormatter.formatUzs(remaining)}",
                                        style = MaterialTheme.typography.labelMedium.copy(fontWeight = FontWeight.Bold),
                                        color = if (remaining > 0) Amber600 else Emerald600
                                    )
                                }

                                Row(
                                    modifier = Modifier.fillMaxWidth(),
                                    horizontalArrangement = Arrangement.End
                                ) {
                                    FilledTonalButton(
                                        onClick = { selectedWorkerForPayment = item },
                                        shape = RoundedCornerShape(8.dp),
                                        colors = ButtonDefaults.filledTonalButtonColors(
                                            containerColor = Emerald50,
                                            contentColor = Emerald600
                                        )
                                    ) {
                                        Icon(Icons.Default.Payment, contentDescription = null, modifier = Modifier.size(16.dp))
                                        Spacer(modifier = Modifier.width(4.dp))
                                        Text("To'lov qilish")
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
