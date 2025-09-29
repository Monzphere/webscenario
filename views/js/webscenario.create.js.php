<?php
/*
** WebScenario Manager JavaScript
*/

/**
 * @var CView $this
 * @var array $data
 */
?>
<script>
const webScenarioWizard = {
    currentStep: 1,
    totalSteps: 3,
    webSteps: [],
    scenarioVariables: [],
    scenarioHeaders: [],
    data: {},

    init: function(data) {
        this.data = data || {};
        this.selectedHost = {
            id: data.hostid || '',
            name: data.host_name || ''
        };
        this.initializeWizard();
        this.bindEvents();
        this.initHostSearch();
    },

    initHostSearch: function() {
        const hostSearch = document.getElementById('host_search');
        const hostIdField = document.getElementById('hostid');
        const resultsDiv = document.getElementById('host_search_results');

        if (!hostSearch || !resultsDiv) return;

        let searchTimeout = null;

        
        if (this.selectedHost.id && this.selectedHost.name) {
            hostSearch.value = this.selectedHost.name;
            if (hostIdField) hostIdField.value = this.selectedHost.id;
        }

        
        hostSearch.addEventListener('input', (e) => {
            clearTimeout(searchTimeout);
            const searchValue = e.target.value.trim();

            
            if (searchValue.length === 0) {
                this.selectedHost = { id: '', name: '' };
                hostSearch.classList.remove('host-selected');
                if (hostIdField) hostIdField.value = '';
                resultsDiv.classList.remove('show');
                return;
            }

            
            if (searchValue !== this.selectedHost.name) {
                if (searchValue.length < 2) {
                    resultsDiv.classList.remove('show');
                    return;
                }

                searchTimeout = setTimeout(() => {
                    this.searchHosts(searchValue);
                }, 300);
            }
        });

        
        hostSearch.addEventListener('focus', (e) => {
            
            if (this.selectedHost.id && e.target.value === this.selectedHost.name) {
                
                
                
                
            }
        });

        
        document.addEventListener('click', (e) => {
            if (!hostSearch.contains(e.target) && !resultsDiv.contains(e.target)) {
                resultsDiv.classList.remove('show');
            }
        });
    },

    searchHosts: function(search) {
        const resultsDiv = document.getElementById('host_search_results');

        
        const requestData = {
            jsonrpc: '2.0',
            method: 'search',
            params: {
                search: search
            },
            id: Math.floor(Math.random() * 1000)
        };

        

        
        fetch('/jsrpc.php?output=json-rpc', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify(requestData)
        })
        .then(response => response.json())
        .then(data => {
            

            if (data.result && data.result.length > 0) {
                this.showHostResults(data.result);
            } else {
                resultsDiv.innerHTML = '<div class="host-search-no-results">No hosts found</div>';
                resultsDiv.classList.add('show');
            }
        })
        .catch(error => {
            console.error('Error searching hosts:', error);
            resultsDiv.innerHTML = '<div class="host-search-no-results">Error searching hosts</div>';
            resultsDiv.classList.add('show');
        });
    },

    showHostResults: function(hosts) {
        const resultsDiv = document.getElementById('host_search_results');
        const hostSearch = document.getElementById('host_search');
        const hostIdField = document.getElementById('hostid');

        resultsDiv.innerHTML = '';

        hosts.forEach(host => {
            const item = document.createElement('div');
            item.className = 'host-search-item';
            item.textContent = host.name;
            item.dataset.hostid = host.hostid;

            
            if (this.selectedHost.id === host.hostid) {
                item.classList.add('selected');
            }

            item.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();

                
                this.selectedHost = {
                    id: host.hostid,
                    name: host.name
                };

                
                hostSearch.value = host.name;
                hostSearch.classList.add('host-selected');
                if (hostIdField) {
                    hostIdField.value = host.hostid;
                }

                
                resultsDiv.innerHTML = '';
                resultsDiv.classList.remove('show');

                
                hostSearch.blur();

                
            });

            resultsDiv.appendChild(item);
        });

        resultsDiv.classList.add('show');
    },

    initializeWizard: function() {
        

        
        const firstStepContent = document.querySelector('.step-content[data-step="1"]');
        if (firstStepContent) {
            firstStepContent.classList.add('active');
            
        }

        
        const formFields = document.querySelectorAll('.step-content.active input, .step-content.active select, .step-content.active textarea');
        formFields.forEach(field => {
            field.style.display = '';
            field.style.visibility = 'visible';
            
        });

        this.updateStepDisplay();

        
    },

    bindEvents: function() {
        
        const prevBtn = document.getElementById('prev-step');
        const nextBtn = document.getElementById('next-step');
        const createBtn = document.getElementById('create-webscenario');

        if (prevBtn) {
            prevBtn.onclick = () => this.previousStep();
        }
        if (nextBtn) {
            nextBtn.onclick = () => this.nextStep();
        }
        if (createBtn) {
            createBtn.onclick = () => this.createWebScenario();
        }

        
    },

    nextStep: function() {
        if (this.currentStep < this.totalSteps) {
            if (this.validateCurrentStep()) {
                this.currentStep++;
                this.updateStepDisplay();
            }
        }
    },

    previousStep: function() {
        if (this.currentStep > 1) {
            this.currentStep--;
            this.updateStepDisplay();
        }
    },

    updateStepDisplay: function() {
        

        
        document.querySelectorAll(".step-item").forEach((item, index) => {
            const stepNum = index + 1;
            item.classList.remove("active", "completed");

            if (stepNum < this.currentStep) {
                item.classList.add("completed");
            } else if (stepNum === this.currentStep) {
                item.classList.add("active");
            }
        });

        
        document.querySelectorAll(".step-content").forEach((content) => {
            content.classList.remove("active");
        });

        
        const activeContent = document.querySelector(`.step-content[data-step="${this.currentStep}"]`);
        if (activeContent) {
            activeContent.classList.add("active");
        }

        
        const prevBtn = document.getElementById("prev-step");
        const nextBtn = document.getElementById("next-step");
        const createBtn = document.getElementById("create-webscenario");

        if (prevBtn) prevBtn.style.display = this.currentStep > 1 ? "inline-block" : "none";
        if (nextBtn) nextBtn.style.display = this.currentStep < this.totalSteps ? "inline-block" : "none";
        if (createBtn) createBtn.style.display = this.currentStep === this.totalSteps ? "inline-block" : "none";

        
        if (this.currentStep === 2) {
            this.loadWebStepsEditor();
        } else if (this.currentStep === 3) {
            this.loadReviewContent();
        }
    },

    validateCurrentStep: function() {
        if (this.currentStep === 1) {
            const name = document.getElementById("name");
            const hostid = document.getElementById("hostid");

            
            
            
            

            if (!name || !name.value.trim()) {
                alert("Please enter a name for the web scenario");
                return false;
            }

            if (!hostid || !hostid.value || hostid.value === "0") {
                alert("Please select a host");
                return false;
            }
        }
        return true;
    },

    loadWebStepsEditor: function() {
        const container = document.getElementById("steps-container");
        if (container && container.innerHTML.trim() === "") {
            container.innerHTML = `
                <div class="steps-table">
                    <table class="list-table">
                        <thead>
                            <tr>
                                <th>Step</th>
                                <th>Name</th>
                                <th>URL</th>
                                <th>Timeout</th>
                                <th>Required string</th>
                                <th>Status codes</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="steps-tbody">
                            <!-- Steps will be added here -->
                        </tbody>
                    </table>
                </div>
            `;
            this.renderWebSteps();
        }
    },

    addWebScenarioStep: function() {
        
        const stepNum = this.webSteps.length + 1;
        const newStep = {
            no: stepNum,
            name: `Step ${stepNum}`,
            url: "",
            timeout: "15s",
            required: "",
            status_codes: "",
            post_type: 1,
            posts: "",
            follow_redirects: 1,
            retrieve_mode: 0,
            query_fields: [],
            post_fields: [],
            headers: [],
            variables: []
        };

        this.openStepEditModal(newStep, true);
    },

    openStepEditModal: function(step, isNew = false) {
        const self = this;
        const stepNo = step.no;

        
        const stepData = {
            templated: 0,
            httpstepid: step.httpstepid || 0,
            old_name: isNew ? '' : (step.name || ''),
            name: step.name || `Step ${step.no}`,
            url: step.url || '',
            query_fields: step.query_fields || [{name: '', value: ''}],
            post_type: step.post_type || 1,
            post_fields: step.post_fields || [{name: '', value: ''}],
            posts: step.posts || '',
            variables: step.variables || [{name: '', value: ''}],
            headers: step.headers || [{name: '', value: ''}],
            follow_redirects: step.follow_redirects || 1,
            retrieve_mode: step.retrieve_mode || 0,
            timeout: step.timeout || '15s',
            required: step.required || '',
            status_codes: step.status_codes || ''
        };

        
        const overlay = PopUp('webscenario.step.edit', stepData, {
            dialogueid: 'webscenario-step-edit',
            dialogue_class: 'modal-popup-medium',
            prevent_navigation: true
        });

        
        overlay.$dialogue[0].addEventListener('dialogue.submit', function(event) {
            

            const response = event.detail;
            if (response) {
                
                const updatedStep = {
                    no: stepNo,
                    name: response.name || `Step ${stepNo}`,
                    url: response.url || '',
                    timeout: response.timeout || '15s',
                    required: response.required || '',
                    status_codes: response.status_codes || '',
                    post_type: parseInt(response.post_type) || 1,
                    posts: response.posts || '',
                    follow_redirects: parseInt(response.follow_redirects) || 1,
                    retrieve_mode: parseInt(response.retrieve_mode) || 0,
                    query_fields: response.query_fields || [],
                    post_fields: response.post_fields || [],
                    headers: response.headers || [],
                    variables: response.variables || []
                };

                if (isNew) {
                    
                    self.webSteps.push(updatedStep);
                } else {
                    
                    const index = self.webSteps.findIndex(s => s.no === stepNo);
                    if (index >= 0) {
                        
                        self.webSteps[index] = updatedStep;
                    }
                }

                self.renderWebSteps();
                
            }
        }, {once: true}); 
    },

    renderWebSteps: function() {
        const tbody = document.getElementById("steps-tbody");
        if (!tbody) return;

        tbody.innerHTML = "";

        if (this.webSteps.length === 0) {
            const row = document.createElement("tr");
            row.innerHTML = `
                <td colspan="7" style="text-align: center; color: #6c757d; padding: 20px;">
                    No steps configured. Click "Add Step" to create your first step.
                </td>
            `;
            tbody.appendChild(row);
            return;
        }

        this.webSteps.forEach((step, index) => {
            const row = document.createElement("tr");
            
            const statusCodesDisplay = step.status_codes || '200';

            row.innerHTML = `
                <td>${step.no}</td>
                <td><strong>${step.name}</strong></td>
                <td class="text-truncate" style="max-width: 200px;" title="${step.url}">${step.url}</td>
                <td>${step.timeout || "15s"}</td>
                <td class="text-truncate" style="max-width: 150px;" title="${step.required || ''}">${step.required || '-'}</td>
                <td>${statusCodesDisplay}</td>
                <td class="nowrap">
                    <button type="button" onclick="webScenarioWizard.removeStep(${index})" class="btn-link">Remove</button>
                </td>
            `;
            tbody.appendChild(row);
        });
    },


    removeStep: function(index) {
        this.webSteps.splice(index, 1);
        
        this.webSteps.forEach((step, i) => {
            step.no = i + 1;
        });
        this.renderWebSteps();
    },

    loadReviewContent: function() {
        const reviewContainer = document.getElementById("review-content");
        if (!reviewContainer) return;

        
        let hostText = this.selectedHost.name || "Not selected";

        

        
        const agentSelect = document.getElementById("agent");
        let agentText = "";
        if (agentSelect && agentSelect.selectedOptions && agentSelect.selectedOptions.length > 0) {
            agentText = agentSelect.selectedOptions[0].text;
        } else if (agentSelect && agentSelect.options && agentSelect.selectedIndex >= 0) {
            agentText = agentSelect.options[agentSelect.selectedIndex]?.text || agentSelect.value || "";
        }

        const formData = {
            name: document.getElementById("name")?.value || "",
            hostid: hostText,
            delay: document.getElementById("delay")?.value || "1m",
            retries: document.getElementById("retries")?.value || "1",
            agent: agentText,
            steps: this.webSteps
        };

        reviewContainer.innerHTML = `
            <div class="review-section">
                <h5>Basic Configuration</h5>
                <table class="review-table">
                    <tr><td><strong>Name:</strong></td><td>${formData.name}</td></tr>
                    <tr><td><strong>Host:</strong></td><td>${formData.hostid}</td></tr>
                    <tr><td><strong>Update interval:</strong></td><td>${formData.delay}</td></tr>
                    <tr><td><strong>Attempts:</strong></td><td>${formData.retries}</td></tr>
                    <tr><td><strong>User agent:</strong></td><td>${formData.agent}</td></tr>
                </table>
            </div>
            <div class="review-section">
                <h5>Web Steps (${formData.steps.length})</h5>
                <table class="review-table">
                    ${formData.steps.map(step => `
                        <tr>
                            <td><strong>Step ${step.no}:</strong></td>
                            <td>
                                ${step.name}<br>
                                <small>URL: ${step.url}</small><br>
                                <small>Status codes: ${step.status_codes || '200'}</small>
                            </td>
                        </tr>
                    `).join("")}
                </table>
            </div>
        `;
    },

    createWebScenario: function() {
        
        if (this.webSteps.length === 0) {
            alert("At least one step is required");
            return;
        }

        
        const unsavedSteps = this.webSteps.filter(step => step.editing);
        if (unsavedSteps.length > 0) {
            alert("Please save all steps before creating the web scenario");
            return;
        }

        
        for (let i = 0; i < this.webSteps.length; i++) {
            const step = this.webSteps[i];
            if (!step.name || !step.name.trim()) {
                alert(`Step ${step.no}: Name is required`);
                return;
            }
            if (!step.url || !step.url.trim()) {
                alert(`Step ${step.no}: URL is required`);
                return;
            }
        }

        
        const formData = new FormData(document.getElementById("webscenario-create-form"));

        
        const statusCheckbox = document.querySelector('input[name="status"]');
        if (statusCheckbox) {
            
            formData.delete('status');
            
            formData.append('status', statusCheckbox.checked ? '0' : '1');
        }

        formData.append("steps_data", JSON.stringify(this.webSteps));

        if (this.scenarioVariables.length > 0) {
            formData.append("variables", JSON.stringify(this.scenarioVariables));
        }
        if (this.scenarioHeaders.length > 0) {
            formData.append("headers", JSON.stringify(this.scenarioHeaders));
        }

        

        
        const createBtn = document.getElementById("create-webscenario");
        const originalText = createBtn.textContent;
        createBtn.textContent = "Creating...";
        createBtn.disabled = true;

        
        fetch("zabbix.php?action=webscenario.update", {
            method: "POST",
            headers: {
                'Accept': 'application/json'
            },
            body: formData
        })
        .then(response => {
            
            

            
            return response.text().then(text => {
                

                
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error("Failed to parse JSON:", e);
                    throw new Error("Server returned invalid JSON response");
                }
            });
        })
        .then(data => {
            

            if (data && data.success) {
                alert(data.message || "Web scenario created successfully!");
                
                if (window.overlays_stack && overlays_stack.length > 0) {
                    
                    const overlay = overlays_stack.getById('webscenario-create');
                    if (overlay) {
                        overlayDialogueDestroy(overlay.dialogueid);
                    }
                    
                    location.reload();
                } else {
                    window.location.href = "zabbix.php?action=webscenario.list";
                }
            } else {
                const errorMessage = data && data.error ? data.error : "Server error. Check logs for details.";
                alert("Error: " + errorMessage);
            }
        })
        .catch(error => {
            console.error("Request error:", error);
            alert("Error: " + error.message);
        })
        .finally(() => {
            
            createBtn.textContent = originalText;
            createBtn.disabled = false;
        });
    },

    
    
};


window.nextStep = () => webScenarioWizard.nextStep();
window.previousStep = () => webScenarioWizard.previousStep();
window.addWebScenarioStep = () => webScenarioWizard.addWebScenarioStep();
window.createWebScenario = () => webScenarioWizard.createWebScenario();
</script>