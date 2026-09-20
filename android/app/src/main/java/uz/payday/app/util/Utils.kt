package uz.payday.app.util

import android.content.Context
import android.content.Intent
import androidx.core.content.FileProvider
import org.apache.poi.ss.usermodel.FillPatternType
import org.apache.poi.ss.usermodel.IndexedColors
import org.apache.poi.xssf.usermodel.XSSFWorkbook
import uz.payday.app.domain.model.Worker
import java.io.File
import java.io.FileOutputStream
import java.text.DecimalFormat
import java.text.DecimalFormatSymbols
import java.text.SimpleDateFormat
import java.util.*

object CurrencyFormatter {
    private val symbols = DecimalFormatSymbols(Locale.US).apply {
        groupingSeparator = ' '
    }
    private val decimalFormat = DecimalFormat("#,###", symbols)

    fun formatUzs(amount: Double): String {
        return "${decimalFormat.format(amount)} so'm"
    }

    fun formatNumber(number: Number): String {
        return decimalFormat.format(number)
    }
}

object DateUtils {
    private val apiDateFormat = SimpleDateFormat("yyyy-MM-dd", Locale.US)
    private val displayDateFormat = SimpleDateFormat("yyyy-MM-dd", Locale.US)
    private val displayDateTimeFormat = SimpleDateFormat("yyyy-MM-dd HH:mm:ss", Locale.US)
    private val displayTimeFormat = SimpleDateFormat("HH:mm:ss", Locale.US)
    private val monthYearFormat = SimpleDateFormat("yyyy-MM", Locale.US)

    fun today(): String = apiDateFormat.format(Date())

    fun currentMonth(): String {
        val cal = Calendar.getInstance()
        val month = (cal.get(Calendar.MONTH) + 1).toString().padStart(2, '0')
        val year = cal.get(Calendar.YEAR)
        return "$year-$month"
    }

    fun formatDisplayDate(dateStr: String?): String {
        if (dateStr.isNullOrBlank()) return "-"
        return try {
            val cleanStr = if (dateStr.contains("T")) dateStr.substringBefore("T") else if (dateStr.contains(" ")) dateStr.substringBefore(" ") else dateStr
            val date = apiDateFormat.parse(cleanStr)
            if (date != null) displayDateFormat.format(date) else cleanStr
        } catch (e: Exception) {
            dateStr
        }
    }

    fun formatDisplayDateTime(dateTimeStr: String?): String {
        if (dateTimeStr.isNullOrBlank()) return "-"
        return try {
            val normalized = dateTimeStr.replace("T", " ").substringBefore(".")
            if (normalized.length == 19) {
                normalized
            } else {
                val parsed = SimpleDateFormat("yyyy-MM-dd HH:mm:ss", Locale.US).parse(normalized)
                    ?: SimpleDateFormat("yyyy-MM-dd", Locale.US).parse(normalized)
                if (parsed != null) displayDateTimeFormat.format(parsed) else dateTimeStr
            }
        } catch (e: Exception) {
            dateTimeStr
        }
    }

    fun formatDisplayTime(timeStr: String?): String {
        if (timeStr.isNullOrBlank()) return "-"
        return try {
            if (timeStr.contains("T") || timeStr.contains(" ")) {
                val timePart = if (timeStr.contains("T")) timeStr.substringAfter("T") else timeStr.substringAfter(" ")
                val cleanTime = timePart.substringBefore(".")
                if (cleanTime.length == 5) "$cleanTime:00" else cleanTime
            } else if (timeStr.matches(Regex("^\\d{2}:\\d{2}$"))) {
                "$timeStr:00"
            } else {
                timeStr
            }
        } catch (e: Exception) {
            timeStr
        }
    }

    fun formatMinutesToHours(minutes: Int?): String {
        if (minutes == null || minutes <= 0) return "0s 00d"
        val h = minutes / 60
        val m = minutes % 60
        return "${h}s ${m.toString().padStart(2, '0')}d"
    }
}

object ExcelGenerator {

    fun generateWorkersExcel(context: Context, workers: List<Worker>): File {
        val workbook = XSSFWorkbook()
        val sheet = workbook.createSheet("Xodimlar")

        // Header Style
        val headerFont = workbook.createFont().apply {
            bold = true
            color = IndexedColors.WHITE.index
        }
        val headerStyle = workbook.createCellStyle().apply {
            fillForegroundColor = IndexedColors.INDIGO.index
            fillPattern = FillPatternType.SOLID_FOREGROUND
            setFont(headerFont)
        }

        // Headers
        val headers = arrayOf("ID", "F.I.SH", "Filial", "Telefon", "Ish vaqti", "Soatlik stavka", "Kelmagan", "Kechikkan (min)", "Ishlagan (soat)", "Status")
        val headerRow = sheet.createRow(0)
        for (i in headers.indices) {
            val cell = headerRow.createCell(i)
            cell.setCellValue(headers[i])
            cell.cellStyle = headerStyle
        }

        // Data Rows
        for (rowIdx in workers.indices) {
            val worker = workers[rowIdx]
            val row = sheet.createRow(rowIdx + 1)
            row.createCell(0).setCellValue(worker.id.toDouble())
            row.createCell(1).setCellValue(worker.name)
            row.createCell(2).setCellValue(worker.branch?.name ?: "-")
            row.createCell(3).setCellValue(worker.phone ?: "-")
            row.createCell(4).setCellValue("${worker.workTime ?: "-"} - ${worker.endTime ?: "-"}")
            row.createCell(5).setCellValue(worker.hourPrice)
            row.createCell(6).setCellValue((worker.lateDays ?: 0).toDouble())
            row.createCell(7).setCellValue((worker.lateMinutes ?: 0).toDouble())
            row.createCell(8).setCellValue(DateUtils.formatMinutesToHours(worker.workedMinutes))
            row.createCell(9).setCellValue(if (worker.status == 1) "Faol" else "Nofaol")
        }

        for (i in headers.indices) {
            sheet.autoSizeColumn(i)
        }

        val exportDir = File(context.cacheDir, "exports")
        if (!exportDir.exists()) exportDir.mkdirs()

        val file = File(exportDir, "Payday_Xodimlar_${System.currentTimeMillis()}.xlsx")
        FileOutputStream(file).use { out ->
            workbook.write(out)
        }
        workbook.close()
        return file
    }

    fun generateAttendanceExcel(context: Context, data: Map<String, Any>): File {
        val workbook = XSSFWorkbook()
        val sheet = workbook.createSheet("Davomat")

        val headerRow = sheet.createRow(0)
        headerRow.createCell(0).setCellValue("F.I.SH")
        headerRow.createCell(1).setCellValue("Filial")
        headerRow.createCell(2).setCellValue("Keldi")
        headerRow.createCell(3).setCellValue("Ketdi")
        headerRow.createCell(4).setCellValue("Kechikish")
        headerRow.createCell(5).setCellValue("Ishlangan vaqt")

        val exportDir = File(context.cacheDir, "exports")
        if (!exportDir.exists()) exportDir.mkdirs()

        val file = File(exportDir, "Payday_Davomat_${System.currentTimeMillis()}.xlsx")
        FileOutputStream(file).use { out ->
            workbook.write(out)
        }
        workbook.close()
        return file
    }
}

object FileOpener {
    fun shareFile(context: Context, file: File, title: String = "Eksport qilingan fayl") {
        val uri = FileProvider.getUriForFile(
            context,
            "${context.packageName}.fileprovider",
            file
        )
        val intent = Intent(Intent.ACTION_SEND).apply {
            type = "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
            putExtra(Intent.EXTRA_STREAM, uri)
            putExtra(Intent.EXTRA_SUBJECT, title)
            addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)
        }
        context.startActivity(Intent.createChooser(intent, title).apply {
            addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
        })
    }
}
