            <script>
                (function () {
                    const form = document.getElementById('add-user-wizard-form');
                    if (!form) return;

                    const sections = Array.from(form.querySelectorAll('.wizard-section[data-step]'));
                    const stepPills = Array.from(document.querySelectorAll('#add-user-steps .wizard-step'));
                    const quickRoleSelect = document.getElementById('add-user-role-quick-select');
                    const formRoleField = form.querySelector('[name="role"]');
                    const statusNodes = Array.from(form.querySelectorAll('[data-wizard-status]'));
                    const saveButtons = Array.from(form.querySelectorAll('[data-save-step]'));
                    const draftKey = 'admin-add-user-draft';
                    const phoneFields = Array.from(form.querySelectorAll('input[name="phone"], input[name="alternate_mobile"]'));
                    let currentStep = {{ $errors->any() ? 1 : 1 }};

                    const syncRoleField = () => {
                        if (!quickRoleSelect || !formRoleField) return;
                        formRoleField.value = quickRoleSelect.value || formRoleField.value || '';
                    };

                    const setStatus = (message, tone = 'default') => {
                        statusNodes.forEach((node) => {
                            node.textContent = message;
                            node.classList.toggle('saved', tone === 'saved');
                        });
                    };

                    const collectDraft = () => {
                        syncRoleField();
                        const data = {};
                        Array.from(form.elements).forEach((field) => {
                            if (!field.name || field.type === 'file' || field.type === 'submit' || field.type === 'button') return;
                            if ((field.type === 'checkbox' || field.type === 'radio')) {
                                data[field.name] = field.checked ? field.value : (data[field.name] ?? '');
                                return;
                            }
                            data[field.name] = field.value;
                        });
                        data.__step = currentStep;
                        data.__saved_at = new Date().toLocaleString();
                        return data;
                    };

                    const saveDraft = () => {
                        try {
                            const data = collectDraft();
                            window.localStorage.setItem(draftKey, JSON.stringify(data));
                            setStatus(`Saved successfully at ${data.__saved_at}.`, 'saved');
                        } catch (error) {
                            setStatus('Could not save progress in this browser.');
                        }
                    };

                    const restoreDraft = () => {
                        try {
                            const raw = window.localStorage.getItem(draftKey);
                            if (!raw) return;
                            const data = JSON.parse(raw);
                            Object.entries(data).forEach(([name, value]) => {
                                if (name.startsWith('__')) return;
                                const field = form.elements.namedItem(name);
                                if (!field) return;
                                if (field instanceof RadioNodeList) {
                                    Array.from(field).forEach((node) => {
                                        if (node.type === 'checkbox' || node.type === 'radio') {
                                            node.checked = node.value === value;
                                        } else {
                                            node.value = value ?? '';
                                        }
                                    });
                                    return;
                                }
                                if (field.type === 'checkbox' || field.type === 'radio') {
                                    field.checked = field.value === value;
                                    return;
                                }
                                field.value = value ?? '';
                            });

                            if (quickRoleSelect && formRoleField && formRoleField.value) {
                                quickRoleSelect.value = formRoleField.value;
                            }

                            if (data.__saved_at) {
                                setStatus(`Restored saved progress from ${data.__saved_at}.`, 'saved');
                            }

                            if (Number(data.__step) >= 1 && Number(data.__step) <= sections.length && !{{ $errors->any() ? 'true' : 'false' }}) {
                                currentStep = Number(data.__step);
                            }
                        } catch (error) {
                            setStatus('Draft restore skipped.');
                        }
                    };

                    const showStep = (step) => {
                        currentStep = step;
                        sections.forEach((section) => {
                            const sectionStep = Number(section.dataset.step || 1);
                            section.classList.toggle('hidden-step', sectionStep !== step);
                        });
                        stepPills.forEach((pill) => {
                            const pillStep = Number(pill.dataset.step || 1);
                            pill.classList.toggle('active', pillStep === step);
                        });
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                    };

                    const validateStep = (step) => {
                        const section = sections.find((item) => Number(item.dataset.step || 1) === step);
                        if (!section) return true;

                        const requiredFields = Array.from(section.querySelectorAll('[required]'));
                        let isValid = true;
                        for (const field of requiredFields) {
                            field.classList.remove('field-invalid');
                            if (!field.checkValidity()) {
                                field.classList.add('field-invalid');
                                if (isValid) {
                                    field.reportValidity();
                                }
                                isValid = false;
                            }
                        }
                        return isValid;
                    };

                    form.querySelectorAll('[data-next-step]').forEach((button) => {
                        button.addEventListener('click', () => {
                            const next = Number(button.getAttribute('data-next-step') || currentStep + 1);
                            syncRoleField();
                            if (!validateStep(currentStep)) return;
                            saveDraft();
                            showStep(next);
                        });
                    });

                    form.querySelectorAll('[data-prev-step]').forEach((button) => {
                        button.addEventListener('click', () => {
                            const prev = Number(button.getAttribute('data-prev-step') || currentStep - 1);
                            showStep(prev);
                        });
                    });

                    saveButtons.forEach((button) => {
                        button.addEventListener('click', () => {
                            saveDraft();
                        });
                    });

                    stepPills.forEach((pill) => {
                        pill.addEventListener('click', () => {
                            const target = Number(pill.dataset.step || 1);
                            if (target > currentStep && !validateStep(currentStep)) return;
                            showStep(target);
                        });
                    });

                    if (quickRoleSelect && formRoleField) {
                        quickRoleSelect.addEventListener('change', () => {
                            syncRoleField();
                            saveDraft();
                            if (quickRoleSelect.value && currentStep === 1 && validateStep(1)) {
                                showStep(2);
                            }
                        });

                        quickRoleSelect.value = formRoleField.value || quickRoleSelect.value;
                        syncRoleField();
                    }

                    phoneFields.forEach((field) => {
                        field.addEventListener('input', () => {
                            const normalized = (field.value || '').replace(/\D+/g, '').slice(0, 10);
                            if (field.value !== normalized) {
                                field.value = normalized;
                            }
                            field.classList.remove('field-invalid');
                        });
                    });

                    form.addEventListener('input', (event) => {
                        setStatus('You have unsaved changes on this step.');
                        const target = event && event.target;
                        if (target && target.matches('input, select, textarea')) {
                            target.classList.remove('field-invalid');
                        }
                    });

                    form.addEventListener('submit', () => {
                        syncRoleField();
                        try {
                            window.localStorage.removeItem(draftKey);
                        } catch (error) {
                            // Ignore local storage cleanup failures.
                        }
                    });

                    restoreDraft();
                    showStep(currentStep);
                })();
            </script>
