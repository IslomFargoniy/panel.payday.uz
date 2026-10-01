// Maosh formasi sana mantiqi (resources/js/lib/salary-date.ts) uchun sof funksiya testlari.
// Ishga tushirish: npm run test:js  (Node >= 22.18: TypeScript fayllar to'g'ridan-to'g'ri import qilinadi)
import assert from 'node:assert/strict';
import { test } from 'node:test';

import { isSalaryPeriodMismatch, resolveNextSalaryStartDate } from '../../resources/js/lib/salary-date.ts';

test('resolveNextSalaryStartDate: oxirgi maosh + 1 kun oy boshidan keyin bo\'lsa, shu kun qaytariladi', () => {
    assert.equal(resolveNextSalaryStartDate('2026-09-15', '2026-09-01', '2026-09-20'), '2026-09-16');
});

test('resolveNextSalaryStartDate: oy oxiri va yil oxiridan to\'g\'ri o\'tadi', () => {
    assert.equal(resolveNextSalaryStartDate('2026-09-30', '2026-09-01', '2026-10-20'), '2026-10-01');
    assert.equal(resolveNextSalaryStartDate('2026-12-31', '2026-12-01', '2027-01-20'), '2027-01-01');
});

test('resolveNextSalaryStartDate: oxirgi maosh yo\'q bo\'lsa fallback qaytariladi', () => {
    assert.equal(resolveNextSalaryStartDate(null, '2026-09-01', '2026-09-20'), '2026-09-01');
    assert.equal(resolveNextSalaryStartDate(undefined, '2026-09-01', '2026-09-20'), '2026-09-01');
    assert.equal(resolveNextSalaryStartDate(null, null, null), '');
});

test('resolveNextSalaryStartDate: keyingi kun hisobot boshidan katta emas bo\'lsa fallback saqlanadi', () => {
    assert.equal(resolveNextSalaryStartDate('2026-08-31', '2026-09-01', '2026-09-20'), '2026-09-01');
    assert.equal(resolveNextSalaryStartDate('2026-08-15', '2026-09-01', '2026-09-20'), '2026-09-01');
});

test('resolveNextSalaryStartDate: keyingi kun hisobot `to` sanasidan oshsa, fallback saqlanadi (server bilan bir xil)', () => {
    assert.equal(resolveNextSalaryStartDate('2026-09-20', '2026-09-01', '2026-09-20'), '2026-09-01');
    assert.equal(resolveNextSalaryStartDate('2026-09-25', '2026-09-01', '2026-09-20'), '2026-09-01');
});

test('resolveNextSalaryStartDate: yaroqsiz sana fallback qaytaradi', () => {
    assert.equal(resolveNextSalaryStartDate('not-a-date', '2026-09-01', '2026-09-20'), '2026-09-01');
    assert.equal(resolveNextSalaryStartDate('2026-09', '2026-09-01', '2026-09-20'), '2026-09-01');
});

test('server qaytargan davr bilan forma boshlang\'ich sanasi mos keladi: ogohlantirish chiqmaydi', () => {
    // Server: oxirgi maosh 2026-09-15 gacha, from berilmagan => report.from = 2026-09-16, report.to = 2026-09-20
    const report = { from: '2026-09-16', to: '2026-09-20', last_salary_date: '2026-09-15' };
    const formFrom = resolveNextSalaryStartDate(report.last_salary_date, report.from, report.to);

    assert.equal(formFrom, '2026-09-16');
    assert.equal(isSalaryPeriodMismatch(report.from, report.to, formFrom, report.to), false);
});

test('noto\'g\'ri (eski) oraliqda ochilgan hisobot: forma sanasi siljiydi va ogohlantirish chiqadi', () => {
    // Hisobot 1–20 uchun ochilgan, lekin oxirgi maosh 15 gacha berilgan
    const report = { from: '2026-09-01', to: '2026-09-20', last_salary_date: '2026-09-15' };
    const formFrom = resolveNextSalaryStartDate(report.last_salary_date, report.from, report.to);

    assert.equal(formFrom, '2026-09-16');
    assert.equal(isSalaryPeriodMismatch(report.from, report.to, formFrom, report.to), true);
});

test('isSalaryPeriodMismatch: sana qo\'lda o\'zgartirilsa mos kelmaslik aniqlanadi', () => {
    assert.equal(isSalaryPeriodMismatch('2026-09-16', '2026-09-20', '2026-09-16', '2026-09-20'), false);
    assert.equal(isSalaryPeriodMismatch('2026-09-16', '2026-09-20', '2026-09-17', '2026-09-20'), true);
    assert.equal(isSalaryPeriodMismatch('2026-09-16', '2026-09-20', '2026-09-16', '2026-09-19'), true);
});

test('isSalaryPeriodMismatch: bo\'sh qiymatlar mos kelmaslik deb hisoblanmaydi', () => {
    assert.equal(isSalaryPeriodMismatch('', '', '', ''), false);
    assert.equal(isSalaryPeriodMismatch(null, undefined, '2026-09-16', '2026-09-20'), false);
    assert.equal(isSalaryPeriodMismatch('2026-09-16', '2026-09-20', '', ''), false);
});
