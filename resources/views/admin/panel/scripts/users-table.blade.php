            <script>
                (function () {
                    const tableBody = document.getElementById('users-table-body');
                    if (!tableBody) return;

                    const searchInput = document.getElementById('users-search');
                    const pageSizeSelect = document.getElementById('users-page-size');
                    const prevBtn = document.getElementById('users-prev');
                    const nextBtn = document.getElementById('users-next');
                    const info = document.getElementById('users-page-info');
                    const exportBtn = document.getElementById('users-export-btn');
                    const exportMenu = document.getElementById('users-export-menu');
                    const exportCsvBtn = document.getElementById('users-export-csv');

                    const rows = Array.from(tableBody.querySelectorAll('tr')).filter((row) => !row.classList.contains('user-row-details'));
                    const detailsRows = new Map();
                    rows.forEach((row) => {
                        const viewBtn = row.querySelector('.js-view-user');
                        if (viewBtn?.dataset.target) {
                            const details = document.getElementById(viewBtn.dataset.target);
                            if (details) detailsRows.set(row, details);
                        }
                    });

                    let currentPage = 1;
                    let pageSize = Number(pageSizeSelect.value || 25);

                    const normalizedText = (value) => (value || '').toLowerCase();

                    const getFilteredRows = () => {
                        const q = normalizedText(searchInput.value.trim());
                        if (!q) return rows;
                        return rows.filter((row) => {
                            const explicitSearch = normalizedText(row.dataset.search || '');
                            if (explicitSearch.includes(q)) return true;
                            return normalizedText(row.textContent).includes(q);
                        });
                    };

                    const render = () => {
                        const filtered = getFilteredRows();
                        const total = filtered.length;
                        const totalPages = Math.max(1, Math.ceil(total / pageSize));
                        if (currentPage > totalPages) currentPage = totalPages;

                        const start = (currentPage - 1) * pageSize;
                        const end = Math.min(start + pageSize, total);

                        rows.forEach((row) => {
                            row.style.display = 'none';
                            const details = detailsRows.get(row);
                            if (details) details.style.display = 'none';
                        });

                        filtered.slice(start, end).forEach((row, idx) => {
                            row.style.display = '';
                            const serialCell = row.querySelector('.js-serial');
                            if (serialCell) serialCell.textContent = String(start + idx + 1);
                        });

                        const from = total === 0 ? 0 : start + 1;
                        info.textContent = 'Showing ' + from + ' to ' + end + ' of ' + total + ' entries';
                        prevBtn.disabled = currentPage <= 1;
                        nextBtn.disabled = currentPage >= totalPages;
                    };

                    searchInput.addEventListener('input', () => {
                        currentPage = 1;
                        render();
                    });

                    pageSizeSelect.addEventListener('change', () => {
                        pageSize = Number(pageSizeSelect.value || 25);
                        currentPage = 1;
                        render();
                    });

                    prevBtn.addEventListener('click', () => {
                        if (currentPage > 1) {
                            currentPage -= 1;
                            render();
                        }
                    });

                    nextBtn.addEventListener('click', () => {
                        const totalPages = Math.max(1, Math.ceil(getFilteredRows().length / pageSize));
                        if (currentPage < totalPages) {
                            currentPage += 1;
                            render();
                        }
                    });

                    document.querySelectorAll('.js-view-user').forEach((button) => {
                        button.addEventListener('click', () => {
                            const targetId = button.dataset.target;
                            if (!targetId) return;
                            const details = document.getElementById(targetId);
                            if (!details) return;
                            details.style.display = details.style.display === 'none' ? '' : 'none';
                        });
                    });

                    const passwordBackdrop = document.getElementById('password-modal-backdrop');
                    const passwordTitle = document.getElementById('password-modal-title');
                    const passwordForm = document.getElementById('password-change-form');
                    const passwordCancel = document.getElementById('password-cancel');
                    const editUserName = document.getElementById('edit-user-name');
                    const editUserEmail = document.getElementById('edit-user-email');
                    const editUserPhone = document.getElementById('edit-user-phone');

                    const closePasswordModal = () => {
                        passwordBackdrop.style.display = 'none';
                        passwordForm.reset();
                    };

                    document.querySelectorAll('.js-edit-user').forEach((button) => {
                        button.addEventListener('click', () => {
                            const userId = button.dataset.userId;
                            const userName = button.dataset.userName || 'User';
                            const userEmail = button.dataset.userEmail || '';
                            const userPhone = button.dataset.userPhone || '';
                            if (!userId) return;
                            passwordTitle.textContent = 'Edit User - ' + userName;
                            passwordForm.action = '/admin/users/' + userId + '/update';
                            editUserName.value = userName;
                            editUserEmail.value = userEmail;
                            editUserPhone.value = userPhone;
                            passwordBackdrop.style.display = 'flex';
                        });
                    });

                    passwordCancel.addEventListener('click', closePasswordModal);
                    passwordBackdrop.addEventListener('click', (event) => {
                        if (event.target === passwordBackdrop) closePasswordModal();
                    });
                    const historyBackdrop = document.getElementById('history-modal-backdrop');
                    const historyTitle = document.getElementById('history-modal-title');
                    const historyNameInput = document.getElementById('history-filter-name');
                    const historyDateInput = document.getElementById('history-filter-date');
                    const historyTypeSelect = document.getElementById('history-filter-type');
                    const historyFeedback = document.getElementById('history-feedback');
                    const historyTableBody = document.getElementById('history-table-body');
                    const historyCancel = document.getElementById('history-cancel');
                    const historyClear = document.getElementById('history-clear');

                    let historyRecords = [];
                    let activeHistoryUser = null;

                    const getHistoryOptions = (role) => {
                        if (role === 'retailer' || role === 'user') {
                            return [
                                { value: 'all', label: 'Transaction History' },
                                { value: 'deposit', label: 'Deposit History' },
                                { value: 'withdraw', label: 'Withdrawal Transaction History' },
                            ];
                        }

                        return [
                            { value: 'all', label: 'Previous Transaction History' },
                            { value: 'commission', label: 'Commission History' },
                            { value: 'commission_withdraw', label: 'Commission Withdrawal History' },
                        ];
                    };

                    const closeHistoryModal = () => {
                        historyBackdrop.style.display = 'none';
                        historyRecords = [];
                        activeHistoryUser = null;
                        historyNameInput.value = '';
                        historyDateInput.value = '';
                        historyTypeSelect.innerHTML = '';
                        historyFeedback.textContent = 'Select a user to view history.';
                        historyTableBody.innerHTML = '<tr><td colspan="6" class="users-empty">No history loaded</td></tr>';
                    };

                    const formatCurrency = (value) => {
                        const amount = Number(value || 0);
                        return new Intl.NumberFormat('en-IN', {
                            style: 'currency',
                            currency: 'INR',
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2,
                        }).format(amount);
                    };

                    const getHistoryDate = (value) => {
                        if (!value) return '';
                        const date = new Date(value);
                        if (Number.isNaN(date.getTime())) return '';
                        const year = date.getFullYear();
                        const month = String(date.getMonth() + 1).padStart(2, '0');
                        const day = String(date.getDate()).padStart(2, '0');
                        return `${year}-${month}-${day}`;
                    };

                    const renderHistoryRows = () => {
                        if (!activeHistoryUser) {
                            return;
                        }

                        const term = normalizedText(historyNameInput.value.trim());
                        const selectedDate = historyDateInput.value;
                        const selectedType = historyTypeSelect.value || 'all';
                        const isRetailerRole = activeHistoryUser.role === 'retailer' || activeHistoryUser.role === 'user';

                        const filtered = historyRecords.filter((record) => {
                            const haystack = normalizedText([
                                record.name,
                                record.reference,
                                record.description,
                                record.type,
                            ].join(' '));

                            if (term && !haystack.includes(term)) {
                                return false;
                            }

                            if (selectedDate && getHistoryDate(record.created_at) !== selectedDate) {
                                return false;
                            }

                            if (selectedType === 'all') {
                                return true;
                            }

                            if (isRetailerRole) {
                                if (selectedType === 'deposit') {
                                    return record.source === 'wallet' && ['deposit', 'receive'].includes(normalizedText(record.type));
                                }

                                if (selectedType === 'withdraw') {
                                    return record.source === 'wallet' && normalizedText(record.type) === 'withdraw';
                                }
                            } else {
                                if (selectedType === 'commission') {
                                    return record.source === 'commission';
                                }

                                if (selectedType === 'commission_withdraw') {
                                    return record.source === 'wallet' && normalizedText(record.type) === 'withdraw';
                                }
                            }

                            return true;
                        });

                        historyFeedback.textContent = filtered.length
                            ? `Showing ${filtered.length} history records for ${activeHistoryUser.name}`
                            : `No matching history records for ${activeHistoryUser.name}`;

                        if (!filtered.length) {
                            historyTableBody.innerHTML = '<tr><td colspan="6" class="users-empty">No history found</td></tr>';
                            return;
                        }

                        historyTableBody.innerHTML = filtered.map((record) => {
                            const createdAt = record.created_at ? new Date(record.created_at) : null;
                            const dateLabel = createdAt && !Number.isNaN(createdAt.getTime())
                                ? createdAt.toLocaleString()
                                : '-';
                            const details = (record.description || record.reference || '-')
                                .replace(/&/g, '&amp;')
                                .replace(/</g, '&lt;')
                                .replace(/>/g, '&gt;');
                            const typeLabel = String(record.type || '-')
                                .replaceAll('_', ' ')
                                .replace(/\b\w/g, (char) => char.toUpperCase());

                            return `<tr>
                                <td>${dateLabel}</td>
                                <td>${typeLabel}</td>
                                <td>${formatCurrency(record.amount)}</td>
                                <td>${String(record.status || 'completed')}</td>
                                <td>${String(record.reference || '-')}</td>
                                <td>${details}</td>
                            </tr>`;
                        }).join('');
                    };

                    const openHistoryModal = async (userId, userName, userRole) => {
                        activeHistoryUser = {
                            id: userId,
                            name: userName,
                            role: userRole,
                        };

                        historyTitle.textContent = `${userName} History`;
                        historyFeedback.textContent = 'Loading history...';
                        historyBackdrop.style.display = 'flex';
                        historyTableBody.innerHTML = '<tr><td colspan="6" class="users-empty">Loading history...</td></tr>';
                        historyNameInput.value = '';
                        historyDateInput.value = '';
                        historyTypeSelect.innerHTML = getHistoryOptions(userRole)
                            .map((option) => `<option value="${option.value}">${option.label}</option>`)
                            .join('');

                        try {
                            const response = await fetch(`/admin/users/${userId}/history`, {
                                headers: {
                                    'X-Requested-With': 'XMLHttpRequest',
                                    'Accept': 'application/json',
                                },
                            });

                            if (!response.ok) {
                                throw new Error('Failed to load history');
                            }

                            const payload = await response.json();
                            historyRecords = Array.isArray(payload.records) ? payload.records : [];
                            renderHistoryRows();
                        } catch (error) {
                            historyRecords = [];
                            historyFeedback.textContent = 'Failed to load history.';
                            historyTableBody.innerHTML = '<tr><td colspan="6" class="users-empty">Failed to load history</td></tr>';
                        }
                    };

                    document.querySelectorAll('.js-history-filter').forEach((button) => {
                        button.addEventListener('click', () => {
                            openHistoryModal(
                                button.dataset.userId,
                                button.dataset.userName || 'User',
                                button.dataset.userRole || 'retailer'
                            );
                        });
                    });

                    historyNameInput.addEventListener('input', renderHistoryRows);
                    historyDateInput.addEventListener('change', renderHistoryRows);
                    historyTypeSelect.addEventListener('change', renderHistoryRows);

                    historyClear.addEventListener('click', () => {
                        if (!activeHistoryUser) {
                            return;
                        }

                        historyNameInput.value = '';
                        historyDateInput.value = '';
                        historyTypeSelect.value = 'all';
                        renderHistoryRows();
                    });

                    historyCancel.addEventListener('click', closeHistoryModal);
                    historyBackdrop.addEventListener('click', (event) => {
                        if (event.target === historyBackdrop) closeHistoryModal();
                    });

                    exportBtn.addEventListener('click', () => {
                        exportMenu.style.display = exportMenu.style.display === 'block' ? 'none' : 'block';
                    });

                    document.addEventListener('click', (event) => {
                        const inside = exportBtn.contains(event.target) || exportMenu.contains(event.target);
                        if (!inside) exportMenu.style.display = 'none';
                    });

                    const csvEscape = (value) => {
                        const text = String(value ?? '');
                        return '"' + text.replaceAll('"', '""') + '"';
                    };

                    exportCsvBtn.addEventListener('click', () => {
                        const filtered = getFilteredRows();
                        const csvRows = [];
                        csvRows.push(['S.No', 'Name', 'Email', 'Agent ID', 'Role', 'Mobile', 'Status']);

                        filtered.forEach((row, idx) => {
                            const cells = row.querySelectorAll('td');
                            csvRows.push([
                                String(idx + 1),
                                cells[2]?.innerText.trim() || '',
                                cells[3]?.innerText.trim() || '',
                                cells[4]?.innerText.trim() || '',
                                cells[5]?.innerText.trim() || '',
                                cells[6]?.innerText.trim() || '',
                                cells[7]?.innerText.trim() || '',
                            ]);
                        });

                        const csvContent = csvRows
                            .map((row) => row.map((value) => csvEscape(value)).join(','))
                            .join('\n');

                        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
                        const link = document.createElement('a');
                        link.href = URL.createObjectURL(blob);
                        link.download = 'users-export.csv';
                        link.style.display = 'none';
                        document.body.appendChild(link);
                        link.click();
                        URL.revokeObjectURL(link.href);
                        link.remove();
                        exportMenu.style.display = 'none';
                    });

                    render();
                })();
            </script>

