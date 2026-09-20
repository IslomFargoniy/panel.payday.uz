package uz.payday.app.presentation.settings

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
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
import uz.payday.app.presentation.navigation.Screen
import uz.payday.app.presentation.theme.*

@Composable
fun SettingsScreen(
    navController: NavController,
    viewModel: SettingsViewModel = hiltViewModel()
) {
    val uiState by viewModel.uiState.collectAsState()
    var showLogoutDialog by remember { mutableStateOf(false) }

    LaunchedEffect(uiState.isLoggedOut) {
        if (uiState.isLoggedOut) {
            navController.navigate(Screen.Login.route) {
                popUpTo(0) { inclusive = true }
            }
        }
    }

    if (showLogoutDialog) {
        AlertDialog(
            onDismissRequest = { showLogoutDialog = false },
            title = { Text("Chiqish") },
            text = { Text("Haqiqatan ham PayDay tizimidan chiqmoqchimisiz?") },
            confirmButton = {
                TextButton(
                    onClick = {
                        showLogoutDialog = false
                        viewModel.logout()
                    },
                    colors = ButtonDefaults.textButtonColors(contentColor = Rose600)
                ) {
                    Text("Chiqish")
                }
            },
            dismissButton = {
                TextButton(onClick = { showLogoutDialog = false }) {
                    Text("Bekor qilish")
                }
            }
        )
    }

    Scaffold(
        topBar = {
            PaydayTopBar(title = "Sozlamalar")
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
            // Profile Summary Card
            item {
                Card(
                    modifier = Modifier
                        .fillMaxWidth()
                        .clickable { navController.navigate(Screen.Profile.route) },
                    shape = RoundedCornerShape(16.dp),
                    colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
                    elevation = CardDefaults.cardElevation(defaultElevation = 1.dp)
                ) {
                    Row(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(16.dp),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Box(
                            modifier = Modifier
                                .size(50.dp)
                                .clip(RoundedCornerShape(12.dp))
                                .background(Indigo50),
                            contentAlignment = Alignment.Center
                        ) {
                            Icon(
                                imageVector = Icons.Default.Person,
                                contentDescription = null,
                                tint = Indigo600,
                                modifier = Modifier.size(28.dp)
                            )
                        }
                        Spacer(modifier = Modifier.width(14.dp))
                        Column(modifier = Modifier.weight(1f)) {
                            Text(
                                text = uiState.user?.name ?: "Foydalanuvchi",
                                style = MaterialTheme.typography.titleMedium.copy(fontWeight = FontWeight.Bold)
                            )
                            Text(
                                text = uiState.user?.email ?: "admin@payday.uz",
                                style = MaterialTheme.typography.bodyMedium,
                                color = MaterialTheme.colorScheme.onSurfaceVariant
                            )
                        }
                        Icon(
                            imageVector = Icons.Default.ChevronRight,
                            contentDescription = null,
                            tint = MaterialTheme.colorScheme.onSurfaceVariant
                        )
                    }
                }
            }

            // Language & Appearance Card
            item {
                Text(
                    text = "Ilova sozlamalari",
                    style = MaterialTheme.typography.titleMedium.copy(fontWeight = FontWeight.Bold)
                )
            }

            item {
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
                        // Language Selector
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.SpaceBetween,
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Row(verticalAlignment = Alignment.CenterVertically) {
                                Icon(Icons.Outlined.Language, contentDescription = null, tint = Indigo600)
                                Spacer(modifier = Modifier.width(12.dp))
                                Text("Til (Language)", style = MaterialTheme.typography.bodyLarge)
                            }
                            Row(horizontalArrangement = Arrangement.spacedBy(4.dp)) {
                                FilterChip(
                                    selected = uiState.language == "uz",
                                    onClick = { viewModel.setLanguage("uz") },
                                    label = { Text("UZ") }
                                )
                                FilterChip(
                                    selected = uiState.language == "ru",
                                    onClick = { viewModel.setLanguage("ru") },
                                    label = { Text("RU") }
                                )
                                FilterChip(
                                    selected = uiState.language == "en",
                                    onClick = { viewModel.setLanguage("en") },
                                    label = { Text("EN") }
                                )
                            }
                        }

                        Divider(color = Slate100)

                        // Theme Mode
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.SpaceBetween,
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Row(verticalAlignment = Alignment.CenterVertically) {
                                Icon(Icons.Outlined.DarkMode, contentDescription = null, tint = Indigo600)
                                Spacer(modifier = Modifier.width(12.dp))
                                Text("Qorong'i rejim (Dark mode)", style = MaterialTheme.typography.bodyLarge)
                            }
                            Switch(
                                checked = uiState.themeMode == "dark",
                                onCheckedChange = { isDark ->
                                    viewModel.setThemeMode(if (isDark) "dark" else "light")
                                }
                            )
                        }

                        Divider(color = Slate100)

                        // Biometrics
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.SpaceBetween,
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Row(verticalAlignment = Alignment.CenterVertically) {
                                Icon(Icons.Outlined.Fingerprint, contentDescription = null, tint = Indigo600)
                                Spacer(modifier = Modifier.width(12.dp))
                                Text("Biometrik / FaceID", style = MaterialTheme.typography.bodyLarge)
                            }
                            Switch(
                                checked = uiState.isBiometricEnabled,
                                onCheckedChange = viewModel::setBiometricEnabled
                            )
                        }
                    }
                }
            }

            // Security Card
            item {
                Text(
                    text = "Xavfsizlik",
                    style = MaterialTheme.typography.titleMedium.copy(fontWeight = FontWeight.Bold)
                )
            }

            item {
                Card(
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(16.dp),
                    colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface)
                ) {
                    Column(modifier = Modifier.fillMaxWidth()) {
                        SettingsRowItem(
                            icon = Icons.Outlined.LockReset,
                            title = "Parolni o'zgartirish",
                            onClick = { navController.navigate(Screen.ChangePassword.route) }
                        )
                    }
                }
            }

            // Logout Button
            item {
                Spacer(modifier = Modifier.height(8.dp))
                OutlinedButton(
                    onClick = { showLogoutDialog = true },
                    modifier = Modifier
                        .fillMaxWidth()
                        .height(48.dp),
                    shape = RoundedCornerShape(12.dp),
                    colors = ButtonDefaults.outlinedButtonColors(contentColor = Rose600)
                ) {
                    Icon(Icons.Default.Logout, contentDescription = null, modifier = Modifier.size(18.dp))
                    Spacer(modifier = Modifier.width(8.dp))
                    Text(
                        text = "Tizimdan chiqish",
                        style = MaterialTheme.typography.labelLarge.copy(fontWeight = FontWeight.Bold)
                    )
                }
            }
        }
    }
}

@Composable
fun SettingsRowItem(
    icon: ImageVector,
    title: String,
    onClick: () -> Unit
) {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .clickable { onClick() }
            .padding(16.dp),
        verticalAlignment = Alignment.CenterVertically
    ) {
        Icon(icon, contentDescription = null, tint = Indigo600)
        Spacer(modifier = Modifier.width(14.dp))
        Text(
            text = title,
            style = MaterialTheme.typography.bodyLarge,
            modifier = Modifier.weight(1f)
        )
        Icon(
            imageVector = Icons.Default.ChevronRight,
            contentDescription = null,
            tint = MaterialTheme.colorScheme.onSurfaceVariant
        )
    }
}

@Composable
fun ProfileScreen(
    navController: NavController,
    viewModel: SettingsViewModel = hiltViewModel()
) {
    val uiState by viewModel.uiState.collectAsState()
    var name by remember { mutableStateOf(uiState.user?.name ?: "") }
    var email by remember { mutableStateOf(uiState.user?.email ?: "") }
    var phone by remember { mutableStateOf(uiState.user?.phone ?: "") }

    LaunchedEffect(uiState.user) {
        uiState.user?.let {
            name = it.name
            email = it.email
            phone = it.phone ?: ""
        }
    }

    Scaffold(
        topBar = {
            PaydayTopBar(
                title = "Profil",
                navigationIcon = Icons.Default.ArrowBack,
                onNavigationClick = { navController.popBackStack() }
            )
        },
        containerColor = MaterialTheme.colorScheme.background
    ) { paddingValues ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(paddingValues)
                .padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(16.dp)
        ) {
            OutlinedTextField(
                value = name,
                onValueChange = { name = it },
                label = { Text("F.I.SH") },
                singleLine = true,
                modifier = Modifier.fillMaxWidth()
            )
            OutlinedTextField(
                value = email,
                onValueChange = { email = it },
                label = { Text("Email") },
                singleLine = true,
                modifier = Modifier.fillMaxWidth()
            )
            OutlinedTextField(
                value = phone,
                onValueChange = { phone = it },
                label = { Text("Telefon") },
                singleLine = true,
                modifier = Modifier.fillMaxWidth()
            )

            Spacer(modifier = Modifier.height(10.dp))

            Button(
                onClick = { viewModel.updateProfile(name, email, phone.ifBlank { null }) },
                enabled = !uiState.isLoading,
                modifier = Modifier
                    .fillMaxWidth()
                    .height(50.dp),
                shape = RoundedCornerShape(12.dp),
                colors = ButtonDefaults.buttonColors(containerColor = Indigo600)
            ) {
                Text("Saqlash", fontWeight = FontWeight.Bold)
            }
        }
    }
}

@Composable
fun ChangePasswordScreen(
    navController: NavController,
    viewModel: SettingsViewModel = hiltViewModel()
) {
    val uiState by viewModel.uiState.collectAsState()
    var currentPassword by remember { mutableStateOf("") }
    var newPassword by remember { mutableStateOf("") }

    Scaffold(
        topBar = {
            PaydayTopBar(
                title = "Parolni o'zgartirish",
                navigationIcon = Icons.Default.ArrowBack,
                onNavigationClick = { navController.popBackStack() }
            )
        },
        containerColor = MaterialTheme.colorScheme.background
    ) { paddingValues ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(paddingValues)
                .padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(16.dp)
        ) {
            OutlinedTextField(
                value = currentPassword,
                onValueChange = { currentPassword = it },
                label = { Text("Joriy parol") },
                singleLine = true,
                modifier = Modifier.fillMaxWidth()
            )
            OutlinedTextField(
                value = newPassword,
                onValueChange = { newPassword = it },
                label = { Text("Yangi parol") },
                singleLine = true,
                modifier = Modifier.fillMaxWidth()
            )

            Spacer(modifier = Modifier.height(10.dp))

            Button(
                onClick = {
                    if (currentPassword.isNotBlank() && newPassword.isNotBlank()) {
                        viewModel.changePassword(currentPassword, newPassword)
                    }
                },
                enabled = !uiState.isLoading,
                modifier = Modifier
                    .fillMaxWidth()
                    .height(50.dp),
                shape = RoundedCornerShape(12.dp),
                colors = ButtonDefaults.buttonColors(containerColor = Indigo600)
            ) {
                Text("Parolni yangilash", fontWeight = FontWeight.Bold)
            }
        }
    }
}
