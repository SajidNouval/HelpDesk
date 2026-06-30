/**
 * Live Chat Form Handler
 * Handles OTP request and verification for live chat functionality
 */

import { showInlineAlert, hideInlineAlert } from '../utils/notification';
import { getCsrfToken, safeFetch } from '../utils/http';

/**
 * Initialize live chat form handler
 */
export function initLiveChatForm() {
    const liveChatForm = document.getElementById('liveChatForm');
    if (!liveChatForm) return;

    const liveChatOtpStep = document.getElementById('liveChatOtpStep');
    const liveChatOtpCode = document.getElementById('livechat_otp_code');
    const liveChatVerificationToken = document.getElementById('liveChatVerificationToken');
    const liveChatAlert = document.getElementById('liveChatAlert');
    const submitBtn = document.getElementById('submitLiveChatBtn');
    const submitText = submitBtn ? submitBtn.querySelector('.submit-text') : null;
    const submitLoading = submitBtn ? submitBtn.querySelector('.submit-loading') : null;

    /**
     * Set loading state on submit button
     * @param {boolean} isLoading - Whether to show loading state
     */
    function setLoading(isLoading) {
        if (submitBtn) {
            submitBtn.disabled = isLoading;
            if (submitText) submitText.classList.toggle('hidden', isLoading);
            if (submitLoading) submitLoading.classList.toggle('hidden', !isLoading);
        }
    }

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
     * Request OTP for live chat
     */
    async function requestLiveChatOtp() {
        setLoading(true);
        hideInlineAlert(liveChatAlert);

        const nameInput = document.getElementById('livechat_name');
        const emailInput = document.getElementById('livechat_email');
        const subjectInput = document.getElementById('livechat_subject');
        const messageInput = document.getElementById('livechat_message');
        const categoryInput = document.getElementById('livechat_category_id');

        if (!nameInput || !emailInput || !subjectInput || !messageInput || !categoryInput) {
            showInlineAlert(liveChatAlert, 'Formulir tidak lengkap.', 'error');
            setLoading(false);
            return;
        }

        const payload = {
            name: nameInput.value.trim(),
            email: emailInput.value.trim(),
            subject: subjectInput.value.trim(),
            message: messageInput.value.trim(),
            category_id: categoryInput.value,
            type: 'livechat',
        };

        try {
            const response = await safeFetch(liveChatForm.dataset.otpUrl || '/api/tickets/request-otp', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
                body: JSON.stringify(payload),
            });

            if (!response.ok) {
                throw new Error(response.data?.message || 'Gagal mengirim OTP.');
            }

            if (liveChatVerificationToken instanceof HTMLInputElement) {
                liveChatVerificationToken.value = response.data.verification_token;
            }
            if (liveChatOtpStep instanceof HTMLElement) {
                liveChatOtpStep.classList.remove('hidden');
            }
            if (submitText) {
                submitText.textContent = 'Verifikasi OTP';
            }
            showInlineAlert(liveChatAlert, response.data.message, 'success');
        } catch (error) {
            showInlineAlert(liveChatAlert, error.message, 'error');
        } finally {
            setLoading(false);
        }
    }

    /**
     * Verify OTP for live chat
     */
    async function verifyLiveChatOtp() {
        setLoading(true);
        hideInlineAlert(liveChatAlert);

        try {
            const response = await safeFetch(liveChatForm.dataset.verifyUrl || '/api/tickets/verify-otp', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
                body: JSON.stringify({
                    verification_token: liveChatVerificationToken ? liveChatVerificationToken.value : '',
                    otp_code: liveChatOtpCode ? liveChatOtpCode.value.trim() : '',
                }),
            });

            if (!response.ok) {
                throw new Error(response.data?.message || 'OTP tidak valid.');
            }

            showInlineAlert(liveChatAlert, 'Live chat session dimulai. Tunggu staf kami untuk connect...', 'success');
            
            const emailVal = document.getElementById('livechat_email')?.value?.trim();
            if (typeof window.startLiveChatMode === 'function') {
                window.startLiveChatMode(
                    response.data.ticket_id,
                    response.data.ticket_status,
                    emailVal,
                    response.data.queue_position,
                    response.data.estimated_waiting_minutes
                );
            } else {
                if (response.data?.tracking_url) {
                    setTimeout(() => {
                        window.location.href = response.data.tracking_url;
                    }, 1500);
                }
            }
        } catch (error) {
            showInlineAlert(liveChatAlert, error.message, 'error');
        } finally {
            setLoading(false);
        }
    }

    // Form submit handler
    liveChatForm.addEventListener('submit', function (event) {
        event.preventDefault();
        if (liveChatVerificationToken && liveChatVerificationToken.value) {
            verifyLiveChatOtp();
        } else {
            requestLiveChatOtp();
        }
    });
}