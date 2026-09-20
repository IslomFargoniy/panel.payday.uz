package uz.payday.app.presentation.workers

import android.graphics.Bitmap
import android.graphics.BitmapFactory
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material.icons.outlined.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.asImageBitmap
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.dp
import androidx.hilt.navigation.compose.hiltViewModel
import androidx.navigation.NavController
import uz.payday.app.presentation.components.PaydayTopBar
import uz.payday.app.presentation.theme.*
import java.io.File
import java.io.FileOutputStream

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun AddEditWorkerScreen(
    workerId: Int?,
    navController: NavController,
    viewModel: WorkerViewModel = hiltViewModel()
) {
    val context = LocalContext.current
    val formState by viewModel.formState.collectAsState()
    var branchMenuExpanded by remember { mutableStateOf(false) }
    var salaryTypeMenuExpanded by remember { mutableStateOf(false) }

    val cameraLauncher = rememberLauncherForActivityResult(
        contract = ActivityResultContracts.TakePicturePreview()
    ) { bitmap ->
        if (bitmap != null) {
            val file = File(context.cacheDir, "worker_cam_${System.currentTimeMillis()}.jpg")
            FileOutputStream(file).use { out ->
                bitmap.compress(Bitmap.CompressFormat.JPEG, 90, out)
            }
            viewModel.onFormAvatarChange(file)
        }
    }

    val galleryLauncher = rememberLauncherForActivityResult(
        contract = ActivityResultContracts.GetContent()
    ) { uri ->
        if (uri != null) {
            val inputStream = context.contentResolver.openInputStream(uri)
            val file = File(context.cacheDir, "worker_gal_${System.currentTimeMillis()}.jpg")
            FileOutputStream(file).use { out ->
                inputStream?.copyTo(out)
            }
            viewModel.onFormAvatarChange(file)
        }
    }

    LaunchedEffect(workerId) {
        viewModel.initForm(workerId)
    }

    LaunchedEffect(formState.isSaved) {
        if (formState.isSaved) {
            navController.popBackStack()
        }
    }

    Scaffold(
        topBar = {
            PaydayTopBar(
                title = if (workerId != null) "Xodimni tahrirlash" else "Yangi xodim qo'shish",
                navigationIcon = Icons.Default.ArrowBack,
                onNavigationClick = { navController.popBackStack() }
            )
        },
        containerColor = MaterialTheme.colorScheme.background
    ) { paddingValues ->
        if (formState.isLoading) {
            Box(
                modifier = Modifier
                    .fillMaxSize()
                    .padding(paddingValues),
                contentAlignment = Alignment.Center
            ) {
                CircularProgressIndicator(color = Indigo600)
            }
        } else {
            Column(
                modifier = Modifier
                    .fillMaxSize()
                    .verticalScroll(rememberScrollState())
                    .padding(paddingValues)
                    .padding(16.dp),
                verticalArrangement = Arrangement.spacedBy(16.dp)
            ) {
                if (!formState.errorMessage.isNullOrBlank()) {
                    Surface(
                        modifier = Modifier.fillMaxWidth(),
                        shape = RoundedCornerShape(10.dp),
                        color = Rose50
                    ) {
                        Text(
                            text = formState.errorMessage!!,
                            modifier = Modifier.padding(12.dp),
                            color = Rose600,
                            style = MaterialTheme.typography.bodyMedium
                        )
                    }
                }

                // Avatar Photo Card
                Card(
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(16.dp),
                    colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface)
                ) {
                    Row(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(16.dp),
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.spacedBy(16.dp)
                    ) {
                        Box(
                            modifier = Modifier
                                .size(64.dp)
                                .clip(CircleShape)
                                .background(Indigo50)
                                .border(1.5.dp, Indigo600.copy(alpha = 0.5f), CircleShape),
                            contentAlignment = Alignment.Center
                        ) {
                            if (formState.avatarFile != null) {
                                val bitmap = remember(formState.avatarFile) {
                                    BitmapFactory.decodeFile(formState.avatarFile!!.absolutePath)
                                }
                                if (bitmap != null) {
                                    Image(
                                        bitmap = bitmap.asImageBitmap(),
                                        contentDescription = "Avatar",
                                        modifier = Modifier.fillMaxSize(),
                                        contentScale = ContentScale.Crop
                                    )
                                } else {
                                    Icon(Icons.Default.Person, contentDescription = null, tint = Indigo600, modifier = Modifier.size(32.dp))
                                }
                            } else {
                                Icon(Icons.Default.Person, contentDescription = null, tint = Indigo600, modifier = Modifier.size(32.dp))
                            }
                        }

                        Column(
                            modifier = Modifier.weight(1f),
                            verticalArrangement = Arrangement.spacedBy(6.dp)
                        ) {
                            Text(
                                text = "Xodim Rasmi",
                                style = MaterialTheme.typography.titleSmall.copy(fontWeight = FontWeight.Bold)
                            )
                            Row(
                                horizontalArrangement = Arrangement.spacedBy(8.dp)
                            ) {
                                OutlinedButton(
                                    onClick = { cameraLauncher.launch(null) },
                                    shape = RoundedCornerShape(8.dp),
                                    contentPadding = PaddingValues(horizontal = 10.dp, vertical = 6.dp),
                                    modifier = Modifier.height(36.dp)
                                ) {
                                    Icon(Icons.Default.CameraAlt, contentDescription = "Kamera", modifier = Modifier.size(16.dp))
                                    Spacer(modifier = Modifier.width(4.dp))
                                    Text("Kamera", style = MaterialTheme.typography.labelMedium)
                                }

                                OutlinedButton(
                                    onClick = { galleryLauncher.launch("image/*") },
                                    shape = RoundedCornerShape(8.dp),
                                    contentPadding = PaddingValues(horizontal = 10.dp, vertical = 6.dp),
                                    modifier = Modifier.height(36.dp)
                                ) {
                                    Icon(Icons.Default.PhotoLibrary, contentDescription = "Galereya", modifier = Modifier.size(16.dp))
                                    Spacer(modifier = Modifier.width(4.dp))
                                    Text("Galereya", style = MaterialTheme.typography.labelMedium)
                                }
                            }
                        }
                    }
                }

                Card(
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(16.dp),
                    colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface)
                ) {
                    Column(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(16.dp),
                        verticalArrangement = Arrangement.spacedBy(14.dp)
                    ) {
                        Text(
                            text = "Asosiy Ma'lumotlar",
                            style = MaterialTheme.typography.titleMedium.copy(fontWeight = FontWeight.Bold)
                        )

                        // Name
                        OutlinedTextField(
                            value = formState.name,
                            onValueChange = viewModel::onFormNameChange,
                            label = { Text("F.I.SH *") },
                            placeholder = { Text("Masalan: Aliyev Vali") },
                            singleLine = true,
                            shape = RoundedCornerShape(10.dp),
                            modifier = Modifier.fillMaxWidth()
                        )

                        // Branch Dropdown
                        ExposedDropdownMenuBox(
                            expanded = branchMenuExpanded,
                            onExpandedChange = { branchMenuExpanded = !branchMenuExpanded }
                        ) {
                            val selectedBranchName = formState.branches.find { it.id == formState.branchId }?.name ?: "Filialni tanlang"
                            OutlinedTextField(
                                value = selectedBranchName,
                                onValueChange = {},
                                readOnly = true,
                                label = { Text("Filial *") },
                                trailingIcon = { ExposedDropdownMenuDefaults.TrailingIcon(expanded = branchMenuExpanded) },
                                shape = RoundedCornerShape(10.dp),
                                modifier = Modifier
                                    .fillMaxWidth()
                                    .menuAnchor()
                            )
                            ExposedDropdownMenu(
                                expanded = branchMenuExpanded,
                                onDismissRequest = { branchMenuExpanded = false }
                            ) {
                                formState.branches.forEach { branch ->
                                    DropdownMenuItem(
                                        text = { Text(branch.name) },
                                        onClick = {
                                            viewModel.onFormBranchChange(branch.id)
                                            branchMenuExpanded = false
                                        }
                                    )
                                }
                            }
                        }

                        // Phone
                        OutlinedTextField(
                            value = formState.phone,
                            onValueChange = viewModel::onFormPhoneChange,
                            label = { Text("Telefon raqami") },
                            placeholder = { Text("+998 90 123 45 67") },
                            keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Phone),
                            singleLine = true,
                            shape = RoundedCornerShape(10.dp),
                            modifier = Modifier.fillMaxWidth()
                        )
                    }
                }

                // Schedule & Price Card
                Card(
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(16.dp),
                    colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface)
                ) {
                    Column(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(16.dp),
                        verticalArrangement = Arrangement.spacedBy(14.dp)
                    ) {
                        Text(
                            text = "Ish Rejimi va Stavka",
                            style = MaterialTheme.typography.titleMedium.copy(fontWeight = FontWeight.Bold)
                        )

                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.spacedBy(12.dp)
                        ) {
                            OutlinedTextField(
                                value = formState.workTime,
                                onValueChange = viewModel::onFormWorkTimeChange,
                                label = { Text("Boshlanish") },
                                placeholder = { Text("09:00") },
                                singleLine = true,
                                shape = RoundedCornerShape(10.dp),
                                modifier = Modifier.weight(1f)
                            )
                            OutlinedTextField(
                                value = formState.endTime,
                                onValueChange = viewModel::onFormEndTimeChange,
                                label = { Text("Tugash") },
                                placeholder = { Text("18:00") },
                                singleLine = true,
                                shape = RoundedCornerShape(10.dp),
                                modifier = Modifier.weight(1f)
                            )
                        }

                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.spacedBy(12.dp)
                        ) {
                            OutlinedTextField(
                                value = formState.hourPrice,
                                onValueChange = viewModel::onFormHourPriceChange,
                                label = { Text("Soatlik narxi") },
                                keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                                singleLine = true,
                                shape = RoundedCornerShape(10.dp),
                                modifier = Modifier.weight(1f)
                            )
                            OutlinedTextField(
                                value = formState.finePrice,
                                onValueChange = viewModel::onFormFinePriceChange,
                                label = { Text("Jarima narxi") },
                                keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                                singleLine = true,
                                shape = RoundedCornerShape(10.dp),
                                modifier = Modifier.weight(1f)
                            )
                        }

                        // Salary Type Dropdown
                        ExposedDropdownMenuBox(
                            expanded = salaryTypeMenuExpanded,
                            onExpandedChange = { salaryTypeMenuExpanded = !salaryTypeMenuExpanded }
                        ) {
                            val selectedType = if (formState.salaryType == "day") "Kunbay" else "Soatbay"
                            OutlinedTextField(
                                value = selectedType,
                                onValueChange = {},
                                readOnly = true,
                                label = { Text("Hisoblash turi") },
                                trailingIcon = { ExposedDropdownMenuDefaults.TrailingIcon(expanded = salaryTypeMenuExpanded) },
                                shape = RoundedCornerShape(10.dp),
                                modifier = Modifier
                                    .fillMaxWidth()
                                    .menuAnchor()
                            )
                            ExposedDropdownMenu(
                                expanded = salaryTypeMenuExpanded,
                                onDismissRequest = { salaryTypeMenuExpanded = false }
                            ) {
                                DropdownMenuItem(
                                    text = { Text("Soatbay") },
                                    onClick = {
                                        viewModel.onFormSalaryTypeChange("hour")
                                        salaryTypeMenuExpanded = false
                                    }
                                )
                                DropdownMenuItem(
                                    text = { Text("Kunbay") },
                                    onClick = {
                                        viewModel.onFormSalaryTypeChange("day")
                                        salaryTypeMenuExpanded = false
                                    }
                                )
                            }
                        }

                        OutlinedTextField(
                            value = formState.comment,
                            onValueChange = viewModel::onFormCommentChange,
                            label = { Text("Izoh") },
                            maxLines = 3,
                            shape = RoundedCornerShape(10.dp),
                            modifier = Modifier.fillMaxWidth()
                        )
                    }
                }

                // Save Button
                Button(
                    onClick = viewModel::saveWorker,
                    enabled = !formState.isSaving,
                    modifier = Modifier
                        .fillMaxWidth()
                        .height(52.dp),
                    shape = RoundedCornerShape(12.dp),
                    colors = ButtonDefaults.buttonColors(
                        containerColor = Indigo600,
                        contentColor = Color.White
                    )
                ) {
                    if (formState.isSaving) {
                        CircularProgressIndicator(
                            modifier = Modifier.size(24.dp),
                            color = Color.White,
                            strokeWidth = 2.dp
                        )
                    } else {
                        Text(
                            text = if (workerId != null) "O'zgarishlarni saqlash" else "Xodimni saqlash",
                            style = MaterialTheme.typography.titleMedium.copy(fontWeight = FontWeight.Bold)
                        )
                    }
                }

                Spacer(modifier = Modifier.height(20.dp))
            }
        }
    }
}
