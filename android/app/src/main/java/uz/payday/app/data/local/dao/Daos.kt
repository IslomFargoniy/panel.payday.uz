package uz.payday.app.data.local.dao

import androidx.room.*
import kotlinx.coroutines.flow.Flow
import uz.payday.app.data.local.entity.DashboardStatsEntity
import uz.payday.app.data.local.entity.WorkerEntity

@Dao
interface WorkerDao {
    @Query("SELECT * FROM workers ORDER BY name ASC")
    fun getAllWorkers(): Flow<List<WorkerEntity>>

    @Query("SELECT * FROM workers WHERE id = :id")
    suspend fun getWorkerById(id: Int): WorkerEntity?

    @Query("SELECT * FROM workers WHERE branchId = :branchId")
    fun getWorkersByBranch(branchId: Int): Flow<List<WorkerEntity>>

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertWorkers(workers: List<WorkerEntity>)

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertWorker(worker: WorkerEntity)

    @Query("DELETE FROM workers WHERE id = :id")
    suspend fun deleteWorker(id: Int)

    @Query("DELETE FROM workers")
    suspend fun clearWorkers()
}

@Dao
interface DashboardDao {
    @Query("SELECT * FROM dashboard_stats WHERE id = 1")
    fun getStats(): Flow<DashboardStatsEntity?>

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertStats(stats: DashboardStatsEntity)

    @Query("DELETE FROM dashboard_stats")
    suspend fun clearStats()
}
