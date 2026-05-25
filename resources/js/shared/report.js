/**
 * Report Form Handler
 * Handles OTP request and verification for report functionality
 */

import { getCsrfToken, showInlineAlert, hideInlineAlert, closeModalById, showSuccessToast } from './dom';
import { safeFetch } from '../utils/http';

/**
 * Get input value by ID
 * @param {string} id - The input ID
 * @returns {string}
 */
function getInputValue(id) {
    const input = document.getElementById(id);
    return input?.value?.trim() || '';
}

/**
 * Initialize report modal form handler
 */
export function initReportModal() {
    const reportForm = document.getElementById('reportForm');
    if (!(reportForm instanceof HTMLFormElement)) {
        return;
    }

    const reportOtpStep = document.getElementById('reportOtpStep');
    const reportOtpCode = document.getElementById('report_otp_code');
    const reportVerificationToken = document.getElementById('reportVerificationToken');
    const reportAlert = document.getElementById('reportAlert');
    const submitBtn = document.getElementById('submitReportBtn');
    const submitText = reportForm.querySelector('.submit-text');
    const submitLoading = reportForm.querySelector('.submit-loading');
    const requestOtpUrl = reportForm.dataset.requestOtpUrl;
    const verifyOtpUrl = reportForm.dataset.verifyOtpUrl;

    if (!requestOtpUrl || !verifyOtpUrl) {
        return;
    }

    /**
     * Set loading state on submit button
     * @param {boolean} isLoading - Whether to show loading state
     */
    function setLoading(isLoading) {
        if (submitBtn) submitBtn.disabled = isLoading;
        if (submitText) submitText.classList.toggle('hidden', isLoading);
        if (submitLoading) submitLoading.classList.toggle('hidden', !isLoading);
    }

    /**
     * Request OTP for report
     */
    async function requestReportOtp() {
        setLoading(true);
        hideInlineAlert(reportAlert);

        const payload = {
            name: getInputValue('report_name'),
            email: getInputValue('report_email'),
            subject: getInputValue('report_subject'),
            message: getInputValue('report_message'),
            category_id: document.getElementById('report_category_id')?.value || '',
            type: 'report',
        };

        try {
            const response = await safeFetch(requestOtpUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
                body: JSON.stringify(payload),
            });

            if (!response.ok) {
                throw new Error(response.data?.message || 'Gagal mengirim OTP.');
            }

            if (reportVerificationToken instanceof HTMLInputElement) {
                reportVerificationToken.value = response.data.verification_token || '';
            }

            if (reportOtpStep instanceof HTMLElement) {
                reportOtpStep.classList.remove('hidden');
            }

            if (submitText) {
                submitText.textContent = 'Verifikasi OTP';
            }

            showInlineAlert(reportAlert, response.data.message || 'OTP dikirim.', 'success');
        } catch (error) {
            showInlineAlert(reportAlert, error.message || 'Terjadi kesalahan.', 'error');
        } finally {
            setLoading(false);
        }
    }

    /**
     * Verify OTP for report
     */
    async function verifyReportOtp() {
        setLoading(true);
        hideInlineAlert(reportAlert);

        try {
            const response = await safeFetch(verifyOtpUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
                body: JSON.stringify({
                    verification_token: reportVerificationToken?.value || '',
                    otp_code: reportOtpCode?.value?.trim() || '',
                }),
            });

            if (!response.ok) {
                throw new Error(response.data?.message || 'OTP tidak valid.');
            }

            showInlineAlert(reportAlert, 'Laporan berhasil dikirim. Link tracking telah dikirimkan ke email Anda.', 'success');
            reportForm.reset();

            if (reportVerificationToken instanceof HTMLInputElement) {
                reportVerificationToken.value = '';
            }

            if (reportOtpCode instanceof HTMLInputElement) {
                reportOtpCode.value = '';
            }

            if (reportOtpStep instanceof HTMLElement) {
                reportOtpStep.classList.add('hidden');
            }

            if (submitText) {
                submitText.textContent = 'Minta OTP';
            }

            closeModalById('reportModal');
            showSuccessToast('Laporan berhasil dibuat!');
        } catch (error) {
            showInlineAlert(reportAlert, error.message || 'Terjadi kesalahan.', 'error');
        } finally {
            setLoading(false);
        }
    }

    // Form submit handler
    reportForm.addEventListener('submit', function (event) {
        event.preventDefault();
        if (reportVerificationToken?.value) {
            verifyReportOtp();
        } else {
            requestReportOtp();
        }
    });
}