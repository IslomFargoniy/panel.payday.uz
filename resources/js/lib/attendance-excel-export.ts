import { SearchData, WorkerPaginate } from '@/types';
import { differenceInSeconds, format, parseISO } from 'date-fns';
import ExcelJS from 'exceljs';
import { saveAs } from 'file-saver';

// Converts HH:mm:ss string to minutes
const timeToMinutes = (time?: string | null): number => {
    if (!time) return 0;
    const [h, m, s] = time.split(':').map(Number);
    return h * 60 + m + s / 60;
};

// Converts minutes to "HH:mm" format
const minutesToHHMM = (minutes: number): string => {
    const hours = Math.floor(minutes / 60);
    const mins = minutes % 60;
    return `${hours}:${String(mins).padStart(2, '0')}`;
};

// Calculates worked time based on work_time, end_time, and number of days
const calculateWorkedTime = (startTime?: string | null, endTime?: string | null, days: number = 1): string => {
    if (timeToMinutes(endTime) < timeToMinutes(startTime)) {
        return '0:00';
    }

    let diff = timeToMinutes(endTime) - timeToMinutes(startTime);
    if (diff < 0) diff += 24 * 60;
    diff *= days;

    return minutesToHHMM(Math.round(diff));
};

type TranslationFn = (key: string, fallbackOrOptions?: any) => string;

export const exportMonthlyAttendanceToExcel = async (
    worker: WorkerPaginate,
    searchData: SearchData,
    t: TranslationFn
) => {
    if (!worker?.data || worker.data.length === 0) return;

    const workbook = new ExcelJS.Workbook();
    const worksheet = workbook.addWorksheet('Attendance');

    const headers = [
        t('n', '№'),
        t('name', 'F.I.SH'),
        t('firm', 'Firma'),
        t('phone', 'Telefon'),
        t('late_hours', 'Kechikkan soat'),
        t('worked_hours', 'Ishlagan soat'),
        t('late_days', 'Kechikkan kun'),
        t('worked_days', 'Ishlagan kun'),
        t('work_days', 'Ish kuni'),
        t('work_hours', 'Grafik soati'),
    ];

    // Add header row
    const headerRow = worksheet.addRow(headers);
    headerRow.eachCell((cell, colNumber) => {
        const headerText = headers[colNumber - 1];

        if (headerText === t('late_hours') || headerText === t('late_days')) {
            cell.font = { bold: true, color: { argb: 'FFFFFFFF' } };
            cell.fill = {
                type: 'pattern',
                pattern: 'solid',
                fgColor: { argb: 'FFFF0000' },
            };
        } else {
            cell.font = { bold: true };
            cell.fill = {
                type: 'pattern',
                pattern: 'solid',
                fgColor: { argb: 'D9E1F2' },
            };
        }

        cell.alignment = { vertical: 'middle', horizontal: 'center' };
        worksheet.getColumn(colNumber).width = headerText.length + 4;
    });

    // Add data rows
    worker.data.forEach((item, rowIndex) => {
        const row = worksheet.addRow([
            (worker.current_page - 1) * worker.per_page + rowIndex + 1,
            item.name,
            `${item.branch?.firm?.name || ''} ( ${item.branch?.name || ''} )`,
            item.phone,
            `${~~(item.late_minutes! / 60)}:${String(item.late_minutes! % 60).padStart(2, '0')}`,
            `${~~(item.worked_minutes! / 60)}:${String(item.worked_minutes! % 60).padStart(2, '0')}`,
            item.late_days,
            item.worked_days,
            item.work_days,
            calculateWorkedTime(item.work_time, item.end_time, item.work_days ?? 0),
        ]);

        row.getCell(4).font = { color: { argb: 'FFFF0000' } };
        row.getCell(6).font = { color: { argb: 'FF10B981' } };
    });

    const buffer = await workbook.xlsx.writeBuffer();
    const fileName = `${searchData.month || searchData.from || 'Monthly'}_${t('attendance', 'Davomat')}_${format(new Date(), 'yyyy-MM-dd_HH:mm:ss')}.xlsx`;
    saveAs(new Blob([buffer]), fileName);
};

export const exportAttendanceToExcel = async (
    worker: WorkerPaginate,
    searchData: SearchData,
    t: TranslationFn
) => {
    if (!worker?.data || worker.data.length === 0) return;

    const workbook = new ExcelJS.Workbook();
    const worksheet = workbook.addWorksheet(t('attendance', 'Davomat'));

    let allCheckCount = 0;
    let allLateCount = 0;
    let allAbsentCount = 0;

    const daysInMonth = searchData.daysInMonth ?? 30;
    // Header row
    const headerRow = [t('n', '№'), t('name', 'F.I.SH'), t('firm', 'Firma')];
    for (let i = 1; i <= daysInMonth; i++) {
        headerRow.push(String(i));
    }
    headerRow.push(t('on_time', 'O‘z vaqtida'), t('late', 'Kechikkan'), t('absent', 'Kelmadi'));

    worksheet.addRow(headerRow);

    // Set header styling
    worksheet.getRow(1).font = { bold: true };
    worksheet.getRow(1).alignment = { horizontal: 'center' };

    // Data rows
    worker.data?.forEach((item, rowIndex) => {
        let checkCount = 0;
        let lateCount = 0;
        let absentCount = 0;

        const row = [];
        const globalIndex = (worker.current_page - 1) * worker.per_page + rowIndex + 1;
        row.push(globalIndex);
        row.push(item.name);
        row.push(`${item.branch?.firm?.name || ''} (${item.branch?.name || ''})`);

        for (let dayIndex = 0; dayIndex < daysInMonth; dayIndex++) {
            const day = String(dayIndex + 1).padStart(2, '0');
            const dateToCompare = `${searchData.month}-${day}`;

            const dayEvents = item.hikvision_access_events?.filter((event) => {
                return format(new Date(event.created_at), 'yyyy-MM-dd') === dateToCompare;
            });
            const event = dayEvents?.find(e => format(new Date(e.created_at), 'HH:mm:ss') >= '05:00:00') || dayEvents?.[0];
            const isOffDay = item.holidays?.includes(Number(day));

            if (event) {
                const eventTime = format(new Date(event.created_at), 'HH:mm:ss');
                const workTime = event.work_time || item.work_time;

                if (!workTime || eventTime <= workTime) {
                    checkCount++;
                    row.push(t('on_time', 'Vaqtida'));
                } else {
                    lateCount++;
                    row.push(t('late', 'Kech'));
                }
            } else {
                absentCount++;
                if (isOffDay) {
                    row.push(t('off_day', 'Dam'));
                } else {
                    row.push(t('absent', 'Kelmadi'));
                }
            }
        }

        row.push(checkCount, lateCount, absentCount);
        const addedRow = worksheet.addRow(row);

        for (let colIndex = 3; colIndex < 3 + daysInMonth; colIndex++) {
            const cell = addedRow.getCell(colIndex);
            const cellValue = cell.value?.toString().toLowerCase();

            if (cellValue === t('absent', 'Kelmadi').toLowerCase()) {
                cell.font = { color: { argb: 'FFFF0000' } };
            } else if (cellValue === t('late', 'Kech').toLowerCase()) {
                cell.font = { color: { argb: '008000' } };
            }
        }

        allCheckCount += checkCount;
        allLateCount += lateCount;
        allAbsentCount += absentCount;
    });

    // Totals row
    const totalsRow = [t('n', '№'), t('all', 'Jami'), ''];
    for (let i = 0; i < daysInMonth; i++) totalsRow.push('');
    totalsRow.push(allCheckCount.toString(), allLateCount.toString(), allAbsentCount.toString());

    const lastRow = worksheet.addRow(totalsRow.map((cell) => cell.toString()));
    lastRow.font = { bold: true };

    worksheet.columns?.forEach((column) => {
        let maxLength = 10;
        column.eachCell?.({ includeEmpty: true }, (cell) => {
            const cellValue = cell.value ? cell.value.toString() : '';
            if (cellValue.length > maxLength) maxLength = cellValue.length;
        });
        column.width = maxLength + 2;
    });

    const buffer = await workbook.xlsx.writeBuffer();
    const blob = new Blob([buffer], { type: 'application/octet-stream' });
    saveAs(blob, `Attendance_${searchData.month || format(new Date(), 'yyyy-MM')}.xlsx`);
};

export const exportDailyAttendanceToExcel = async (
    worker: WorkerPaginate,
    searchData: SearchData,
    t: TranslationFn
) => {
    if (!worker?.data || worker.data.length === 0) return;

    const workbook = new ExcelJS.Workbook();
    const worksheet = workbook.addWorksheet('Attendance');

    // Header row
    worksheet.addRow([
        t('n', '№'),
        t('name', 'F.I.SH'),
        t('phone', 'Telefon'),
        t('date', 'Sana'),
        t('checkIn', 'Kirdi'),
        t('checkOut', 'Chiqdi'),
        t('late', 'Kechikish'),
        t('worked', 'Ishlagan vaqti'),
    ]);

    // Data rows
    worker.data.forEach((item, rowIndex) => {
        const globalIndex = (worker.current_page - 1) * worker.per_page + rowIndex + 1;

        const checkIns = item.hikvision_access_events?.filter((e) => e.attendanceStatus === 'checkIn') || [];
        const checkOuts = item.hikvision_access_events?.filter((e) => e.attendanceStatus === 'checkOut') || [];

        const checkInTimes = checkIns.length
            ? checkIns.map((ci) => format(new Date(ci.created_at), 'HH:mm:ss')).join(', ')
            : '-';

        const checkOutTimes = checkOuts.length
            ? checkOuts.map((co) => format(new Date(co.created_at), 'HH:mm:ss')).join(', ')
            : '-';

        let lateTime = '-';
        if (checkIns.length > 0) {
            const ci = checkIns[0];
            if (ci.work_time && ci.created_at) {
                const createdAt = parseISO(ci.created_at);
                const datePart = format(createdAt, 'yyyy-MM-dd');
                let workTimeStr = ci.work_time.trim();
                if (/^\d{2}:\d{2}$/.test(workTimeStr)) workTimeStr += ':00';
                if (/^\d{2}:\d{2}:\d{2}$/.test(workTimeStr)) {
                    const workTime = new Date(`${datePart}T${workTimeStr}`);
                    if (createdAt > workTime) {
                        const totalSeconds = differenceInSeconds(createdAt, workTime);
                        const hours = Math.floor(totalSeconds / 3600);
                        const minutes = Math.floor((totalSeconds % 3600) / 60);
                        const seconds = totalSeconds % 60;
                        lateTime = `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
                    }
                }
            }
        }

        let workedTimes = '-';
        if (checkIns.length > 0 && checkOuts.length > 0) {
            const times = checkIns.map((ci, i) => {
                const co = checkOuts[i];
                if (co?.created_at && ci?.created_at) {
                    const inTime = new Date(ci.created_at).getTime();
                    const outTime = new Date(co.created_at).getTime();
                    const diffMs = outTime - inTime;
                    const diffMinutes = Math.floor(diffMs / 60000);
                    const hours = Math.floor(diffMinutes / 60);
                    const minutes = diffMinutes % 60;
                    return `${hours} : ${minutes}`;
                }
                return '-';
            });
            workedTimes = times.join(', ');
        }

        worksheet.addRow([
            globalIndex,
            item.name,
            item.phone,
            item.hikvision_access_events?.[0]?.created_at
                ? format(new Date(item.hikvision_access_events[0].created_at), 'yyyy-MM-dd')
                : '-',
            checkInTimes,
            checkOutTimes,
            lateTime,
            workedTimes,
        ]);
    });

    const buffer = await workbook.xlsx.writeBuffer();
    const blob = new Blob([buffer], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
    saveAs(blob, `Attendance_${searchData.date || format(new Date(), 'yyyy-MM-dd')}.xlsx`);
};
