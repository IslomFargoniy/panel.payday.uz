package uz.payday.app.data.local

import androidx.room.Database
import androidx.room.RoomDatabase
import uz.payday.app.data.local.dao.DashboardDao
import uz.payday.app.data.local.dao.WorkerDao
import uz.payday.app.data.local.entity.DashboardStatsEntity
import uz.payday.app.data.local.entity.WorkerEntity

@Database(
    entities = [WorkerEntity::class, DashboardStatsEntity::class],
    version = 1,
    exportSchema = false
)
abstract class PaydayDatabase : RoomDatabase() {
    abstract fun workerDao(): WorkerDao
    abstract fun dashboardDao(): DashboardDao

    companion object {
        const val DATABASE_NAME = "payday_offline.db"
    }
}
