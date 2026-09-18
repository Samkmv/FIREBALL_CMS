document.addEventListener('DOMContentLoaded', function () {
    const forms = document.querySelectorAll('[data-contact-form]');
    const feedbackBlocks = document.querySelectorAll('[data-support-feedback]');

    forms.forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }

            form.classList.add('was-validated');
        });
    });

    const getCookie = function (name) {
        const value = '; ' + document.cookie;
        const parts = value.split('; ' + name + '=');
        return parts.length === 2 ? parts.pop().split(';').shift() : '';
    };

    const setCookie = function (name, value, maxAge) {
        document.cookie = name + '=' + encodeURIComponent(value) + '; path=/; max-age=' + maxAge + '; SameSite=Lax';
    };

    const storageGet = function (key) {
        try {
            return window.localStorage.getItem(key) || '';
        } catch (error) {
            return '';
        }
    };

    const storageSet = function (key, value) {
        try {
            window.localStorage.setItem(key, value);
        } catch (error) {
            return false;
        }

        return true;
    };

    const decodeCookieValue = function (value) {
        try {
            return decodeURIComponent(value || '');
        } catch (error) {
            return '';
        }
    };

    const randomKey = function () {
        if (window.crypto && window.crypto.randomUUID) {
            return window.crypto.randomUUID();
        }

        const randomPart = Math.random().toString(36).slice(2);
        return Date.now().toString(36) + '-' + randomPart + '-' + randomPart.slice(0, 8);
    };

    const getVisitorKey = function () {
        const storageKey = 'supportFeedbackVisitorKey';
        const cookieKey = 'support_feedback_visitor';
        let visitorKey = storageGet(storageKey) || decodeCookieValue(getCookie(cookieKey));

        if (!visitorKey) {
            visitorKey = randomKey();
            storageSet(storageKey, visitorKey);
            setCookie(cookieKey, visitorKey, 60 * 60 * 24 * 365);
        }

        return visitorKey;
    };

    const getCsrfToken = function () {
        const meta = document.querySelector('meta[name="needCSRFToken"]');
        return meta ? meta.getAttribute('content') || '' : '';
    };

    const disableButtons = function (buttons, votedValue) {
        buttons.forEach(function (button) {
            button.disabled = true;
            if (button.dataset.supportFeedbackVote === votedValue) {
                button.classList.remove('btn-outline-secondary');
                button.classList.add('btn-dark');
            }
        });
    };

    const showFeedbackMessage = function (block, vote, message) {
        const result = block.querySelector('[data-support-feedback-result]');
        if (!result) {
            return;
        }

        result.hidden = false;
        result.className = 'support-feedback-result alert mb-4 ' + (vote === 'not_helpful' ? 'alert-info' : 'alert-success');
        result.textContent = '';

        const text = document.createElement('p');
        text.className = 'mb-0';
        text.textContent = message;
        result.appendChild(text);

    };

    feedbackBlocks.forEach(function (block) {
        const articleId = block.dataset.articleId || '';
        const feedbackUrl = block.dataset.feedbackUrl || '';
        const buttons = block.querySelectorAll('[data-support-feedback-vote]');
        const voteStorageKey = 'supportArticleFeedback:' + articleId;
        const voteCookieKey = 'support_article_feedback_' + articleId;
        const storedVote = storageGet(voteStorageKey) || getCookie(voteCookieKey);

        if (!articleId || !feedbackUrl || !buttons.length) {
            return;
        }

        if (storedVote) {
            disableButtons(buttons, storedVote);
        }

        buttons.forEach(function (button) {
            button.addEventListener('click', function () {
                const vote = button.dataset.supportFeedbackVote || '';
                if (!vote || button.disabled) {
                    return;
                }

                const existingVote = storageGet(voteStorageKey) || getCookie(voteCookieKey);
                if (existingVote) {
                    disableButtons(buttons, existingVote);
                    showFeedbackMessage(block, existingVote, block.dataset.duplicateMessage || '');
                    return;
                }

                buttons.forEach(function (item) {
                    item.disabled = true;
                });

                const payload = new FormData();
                payload.append('article_id', articleId);
                payload.append('vote', vote);
                payload.append('visitor_key', getVisitorKey());

                fetch(feedbackUrl, {
                    method: 'POST',
                    body: payload,
                    headers: {
                        'X-CSRF-Token': getCsrfToken(),
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin'
                })
                    .then(function (response) {
                        if (!response.ok) {
                            throw new Error('Feedback request failed');
                        }

                        return response.json();
                    })
                    .then(function (data) {
                        if (!data || data.status !== 'success') {
                            throw new Error('Feedback was not accepted');
                        }

                        storageSet(voteStorageKey, vote);
                        setCookie(voteCookieKey, vote, 60 * 60 * 24 * 365);
                        disableButtons(buttons, vote);

                        if (data.already_voted) {
                            showFeedbackMessage(block, vote, block.dataset.duplicateMessage || '');
                            return;
                        }

                        if (vote === 'not_helpful') {
                            showFeedbackMessage(block, vote, block.dataset.contactMessage || '');
                        } else {
                            showFeedbackMessage(block, vote, block.dataset.thanksMessage || '');
                        }
                    })
                    .catch(function () {
                        buttons.forEach(function (item) {
                            item.disabled = false;
                        });
                        showFeedbackMessage(block, 'helpful', block.dataset.errorMessage || '');
                    });
            });
        });
    });
});
