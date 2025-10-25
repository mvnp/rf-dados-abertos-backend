<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Consulta de Estabelecimentos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <style>
        .loading-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.8);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }
        .results-container {
            position: relative;
        }
        .fade-in {
            animation: fadeIn 0.3s ease-in;
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
    </style>
</head>
<body class="bg-light">
    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">
                            <i class="bi bi-search"></i> 
                            Consulta de Estabelecimentos
                        </h4>
                    </div>
                    <div class="card-body">
                        <!-- Search Form -->
                        <form id="searchForm">
                            @csrf
                            
                            <!-- Alert container for messages -->
                            <div id="alertContainer"></div>

                            <div class="row mb-3">
                                <!-- Nome Fantasia -->
                                <div class="col-md-6">
                                    <label for="fantasia" class="form-label">Nome Fantasia</label>
                                    <input type="text" class="form-control" id="fantasia" name="fantasia" placeholder="Digite parte do nome fantasia">
                                </div>

                                <!-- CNAE Principal -->
                                <div class="col-md-6">
                                    <label for="cnae_principal" class="form-label">CNAE Principal</label>
                                    <select class="form-select select2" id="cnae_principal" name="cnae_principal">
                                        <option value="">Selecione...</option>
                                        @foreach($cnaes as $cnae)
                                            <option value="{{ $cnae->codigo }}">
                                                {{ $cnae->codigo }} - {{ $cnae->descricao }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <!-- CNPJ -->
                                <div class="col-md-4">
                                    <label for="cnpj" class="form-label">CNPJ Básico (8 dígitos)</label>
                                    <input type="text" class="form-control select2" id="cnpj" name="cnpj" placeholder="12345678" maxlength="8">
                                </div>

                                <!-- CNAE Secundária -->
                                <div class="col-md-8">
                                    <label for="cnae_secundaria" class="form-label">CNAE Secundária</label>
                                    <select class="form-select select2" id="cnae_secundaria" name="cnae_secundaria">
                                        <option value="">Selecione...</option>
                                        @foreach($cnaes as $cnae)
                                            <option value="{{ $cnae->codigo }}">
                                                {{ $cnae->codigo }} - {{ $cnae->descricao }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <!-- CEP -->
                                <div class="col-md-4">
                                    <label for="cep" class="form-label">CEP</label>
                                    <input type="text" class="form-control" id="cep" name="cep" placeholder="12345-678">
                                </div>

                                <!-- Estado -->
                                <div class="col-md-4">
                                    <label for="uf" class="form-label">Estado</label>
                                    <select class="form-select select2" id="uf" name="uf">
                                        <option value="">Selecione...</option>
                                        @foreach($estados as $estado)
                                            <option value="{{ $estado->sigla }}">
                                                {{ $estado->descricao }} ({{ $estado->sigla }})
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <!-- Município -->
                                <div class="col-md-4">
                                    <label for="municipio" class="form-label">Município</label>
                                    <select class="form-select select2" id="municipio" name="municipio" disabled>
                                        <option value="">Selecione um estado primeiro...</option>
                                    </select>
                                </div>
                            </div>

                            <div class="row g-3">
                                <!-- Situação Cadastral -->
                                <div class="col-md-3">
                                    <label for="situacao" class="form-label">Situação Cadastral</label>
                                    <select class="form-select" id="situacao" name="situacao">
                                        <option value="">Todas</option>
                                        <option value="01">Nula</option>
                                        <option value="02" selected>Ativa</option>
                                        <option value="03">Suspensa</option>
                                        <option value="04">Inapta</option>
                                        <option value="08">Baixada</option>
                                    </select>
                                </div>

                                <!-- Capital Social -->
                                <div class="col-md-6">
                                    <label for="capital_entre" class="form-label">Capital Social (Entre)</label>
                                    <div class="input-group">
                                        <span class="input-group-text">R$</span>
                                        <input type="number" class="form-control" id="capital_inicial" 
                                               placeholder="Valor mínimo" step="0.01">
                                        <span class="input-group-text">até</span>
                                        <input type="number" class="form-control" id="capital_final" 
                                               placeholder="Valor máximo" step="0.01">
                                        <input type="hidden" id="capital_entre" name="capital_entre">
                                    </div>
                                </div>

                                <!-- Limite de Resultados -->
                                <div class="col-md-3">
                                    <label for="limit" class="form-label">Limite de Resultados</label>
                                    <select class="form-select" id="limit" name="limit">
                                        <option value="50">25</option>
                                        <option value="100">100</option>
                                        <option value="200">200</option>
                                        <option value="500">500</option>
                                        <option value="1000">1000</option>
                                        <option value="5000">5000</option>
                                        <option value="10000">10000</option>
                                    </select>
                                </div>

                                <!-- Buttons -->
                                <div class="col-12">
                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-primary" id="searchBtn">
                                            <i class="bi bi-search"></i> <span id="searchBtnText">Pesquisar</span>
                                        </button>
                                        <button type="button" class="btn btn-success" id="exportBtn" disabled>
                                            <i class="bi bi-file-earmark-spreadsheet"></i> <span id="exportBtnText">Exportar CSV</span>
                                        </button>
                                        <button type="button" class="btn btn-secondary" id="clearForm">
                                            <i class="bi bi-arrow-clockwise"></i> Limpar
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Results Container -->
                <div class="results-container">
                    <!-- Loading Overlay -->
                    <div class="loading-overlay d-none" id="loadingOverlay">
                        <div class="text-center">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Carregando...</span>
                            </div>
                            <div class="mt-2">Pesquisando estabelecimentos...</div>
                        </div>
                    </div>

                    <!-- Results Table -->
                    <div id="resultsContainer" style="display: none;">
                        <div class="card shadow-sm mt-4 fade-in">
                            <div class="card-header bg-success text-white">
                                <h5 class="mb-0">
                                    <i class="bi bi-table"></i> 
                                    <span id="resultsTitle">Resultados Encontrados</span>
                                </h5>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover mb-0">
                                        <thead class="table-dark">
                                            <tr>
                                                <th class="text-nowrap">CNPJ</th>
                                                <th class="text-nowrap">Telefone</th>
                                                <th class="text-nowrap">E-mail</th>
                                                <th class="text-nowrap">Razão Social</th>
                                                <th class="text-nowrap">Nome Fantasia</th>
                                                <th class="text-nowrap">Situação</th>
                                                <th class="text-nowrap">CNAE Principal</th>
                                                <th class="text-nowrap">UF</th>
                                                <th class="text-nowrap">Município</th>
                                                <th class="text-nowrap">Capital Social</th>
                                            </tr>
                                        </thead>
                                        <tbody id="resultsTableBody">
                                            <!-- Results will be populated here -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- No Results -->
                    <div id="noResultsContainer" style="display: none;">
                        <div class="card shadow-sm mt-4 fade-in">
                            <div class="card-body text-center">
                                <i class="bi bi-search display-1 text-muted"></i>
                                <h5 class="mt-3">Nenhum resultado encontrado</h5>
                                <p class="text-muted">Tente ajustar os filtros de pesquisa</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
        $(document).ready(function() {
            let hasValidResults = false;

            // Initialize Select2
            $('.select2').select2({
                theme: 'bootstrap-5',
                width: '100%',
                placeholder: 'Selecione...',
                allowClear: true
            });

            // Handle UF change to load municipalities
            $('#uf').change(function() {
                const uf = $(this).val();
                const municipioSelect = $('#municipio');
                
                if (uf) {
                    municipioSelect.prop('disabled', true).html('<option value="">Carregando...</option>');
                    
                    $.ajax({
                        url: '{{ route("municipios.index") }}',
                        method: 'GET',
                        data: { uf: uf },
                        success: function(data) {
                            municipioSelect.empty().append('<option value="">Selecione...</option>');
                            
                            const municipios = data.data || data;
                            
                            $.each(municipios, function(i, municipio) {
                                const codigo = municipio.codigo || municipio.id;
                                const nome = municipio.descricao || municipio.nome;
                                municipioSelect.append(`<option value="${codigo}">${nome}</option>`);
                            });
                            
                            municipioSelect.prop('disabled', false);
                        },
                        error: function(xhr, status, error) {
                            console.error('Error loading municipios:', error);
                            showAlert('Erro ao carregar municípios. Tente novamente.', 'danger');
                            municipioSelect.empty()
                                .append('<option value="">Erro ao carregar...</option>')
                                .prop('disabled', true);
                        }
                    });
                } else {
                    municipioSelect.empty()
                        .append('<option value="">Selecione um estado primeiro...</option>')
                        .prop('disabled', true);
                }
            });

            // Handle capital range inputs
            $('#capital_inicial, #capital_final').on('input', function() {
                const inicial = $('#capital_inicial').val();
                const final = $('#capital_final').val();
                
                if (inicial && final) {
                    $('#capital_entre').val(`${inicial},${final}`);
                } else {
                    $('#capital_entre').val('');
                }
            });

            // Handle form submission with AJAX
            $('#searchForm').on('submit', function(e) {
                e.preventDefault();
                performSearch();
            });

            // Handle export button click
            $('#exportBtn').click(function() {
                if (!hasValidResults) {
                    showAlert('Realize uma pesquisa antes de exportar.', 'warning');
                    return;
                }
                
                exportToCSV();
            });

            // Clear form
            $('#clearForm').click(function() {
                clearForm();
            });

            // Format CNPJ input
            $('#cnpj').on('input', function() {
                this.value = this.value.replace(/\D/g, '').substring(0, 8);
            });

            // Format CEP input
            $('#cep').on('input', function() {
                let value = this.value.replace(/\D/g, '');
                if (value.length >= 5) {
                    value = value.substring(0, 5) + '-' + value.substring(5, 8);
                }
                this.value = value;
            });

            // AJAX Search Function
            function performSearch() {
                const formData = $('#searchForm').serialize();
                
                // Show loading
                showLoading(true);
                hideResults();
                clearAlerts();
                
                // Update button state
                $('#searchBtn').prop('disabled', true);
                $('#searchBtnText').text('Pesquisando...');
                
                $.ajax({
                    url: '{{ route("estabelecimentos.index") }}',
                    method: 'GET',
                    data: formData,
                    dataType: 'json',
                    success: function(response) {
                        console.log('Search response:', response);
                        
                        const results = response.data || response;
                        const total = response.count || (Array.isArray(results) ? results.length : 0);
                        
                        if (total > 0) {
                            populateResults(results, total);
                            showResults();
                            hasValidResults = true;
                            $('#exportBtn').prop('disabled', false);
                        } else {
                            showNoResults();
                            hasValidResults = false;
                            $('#exportBtn').prop('disabled', true);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Search error:', error);
                        console.error('Response:', xhr.responseText);
                        
                        let errorMessage = 'Erro ao realizar a pesquisa. Tente novamente.';
                        
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            errorMessage = xhr.responseJSON.message;
                        } else if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                            const errors = Object.values(xhr.responseJSON.errors).flat();
                            errorMessage = errors.join(', ');
                        }
                        
                        showAlert(errorMessage, 'danger');
                        showNoResults();
                        hasValidResults = false;
                        $('#exportBtn').prop('disabled', true);
                    },
                    complete: function() {
                        showLoading(false);
                        $('#searchBtn').prop('disabled', false);
                        $('#searchBtnText').text('Pesquisar');
                    }
                });
            }

            // Export to CSV Function
            function exportToCSV() {
                // Update export button state
                $('#exportBtn').prop('disabled', true);
                $('#exportBtnText').text('Exportando...');
                
                // Get current form data
                const formData = $('#searchForm').serialize();
                
                // Create download URL with form data
                const exportUrl = '{{ route("estabelecimentos.export") }}?' + formData;
                
                // Create temporary link to trigger download
                const link = document.createElement('a');
                link.href = exportUrl;
                link.download = '';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                
                // Show success message and restore button
                setTimeout(function() {
                    $('#exportBtn').prop('disabled', false);
                    $('#exportBtnText').text('Exportar CSV');
                    showAlert('Download iniciado! O arquivo CSV será salvo em sua pasta de downloads.', 'success');
                }, 1000);
            }

            // Show/Hide Loading
            function showLoading(show) {
                if (show) {
                    $('#loadingOverlay').removeClass('d-none');
                } else {
                    $('#loadingOverlay').addClass('d-none');
                }
            }

            // Show Results
            function showResults() {
                $('#resultsContainer').show();
                $('#noResultsContainer').hide();
            }

            // Show No Results
            function showNoResults() {
                $('#resultsContainer').hide();
                $('#noResultsContainer').show();
            }

            // Hide Results
            function hideResults() {
                $('#resultsContainer').hide();
                $('#noResultsContainer').hide();
            }

            // Populate Results Table
            function populateResults(results, total) {
                const tbody = $('#resultsTableBody');
                tbody.empty();
                
                $('#resultsTitle').text(`Resultados Encontrados (${total.toLocaleString()})`);
                
                const situacoes = {
                    '01': ['Nula', 'danger'],
                    '02': ['Ativa', 'success'],
                    '03': ['Suspensa', 'warning'],
                    '04': ['Inapta', 'secondary'],
                    '08': ['Baixada', 'dark']
                };
                
                $.each(results, function(index, estabelecimento) {
                    const cnpj = estabelecimento.cnpj_completo || 
                                (estabelecimento.cnpj_basico + 
                                (estabelecimento.cnpj_ordem || '').padStart(4, '0') + 
                                (estabelecimento.cnpj_dv || '').padStart(2, '0'));
                    
                    const telefone = formatPhone(estabelecimento.ddd_1, estabelecimento.telefone_1);
                    const razaoSocial = (estabelecimento.empresa && estabelecimento.empresa.razao_social) || 'N/A';
                    const nomeFantasia = estabelecimento.nome_fantasia || '-';
                    const emailEstabelecimento = estabelecimento.email || '-';
                    
                    const situacaoInfo = situacoes[estabelecimento.situacao_cadastral] || ['Desconhecida', 'light'];
                    const situacaoBadge = `<span class="badge bg-${situacaoInfo[1]}">${situacaoInfo[0]}</span>`;
                    
                    const cnaePrincipal = estabelecimento.cnae_principal || '-';
                    const uf = estabelecimento.uf || '-';
                    const municipio = estabelecimento.municipio || '-';
                    
                    let capitalSocial = '-';
                    if (estabelecimento.empresa && estabelecimento.empresa.capital_social) {
                        const valor = parseFloat(estabelecimento.empresa.capital_social);
                        capitalSocial = 'R$ ' + valor.toLocaleString('pt-BR', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        });
                    }
                    
                    const row = `
                        <tr>
                            <td class="text-nowrap"><code>${formatCNPJ(cnpj)}</code></td>
                            <td class="text-nowrap">${telefone}</td>
                            <td class="text-nowrap">${emailEstabelecimento}</td>
                            <td class="text-nowrap">${razaoSocial}</td>
                            <td class="text-nowrap">${nomeFantasia}</td>
                            <td class="text-nowrap">${situacaoBadge}</td>
                            <td class="text-nowrap">${cnaePrincipal}</td>
                            <td class="text-nowrap">${uf}</td>
                            <td class="text-nowrap">${municipio}</td>
                            <td class="text-nowrap">${capitalSocial}</td>
                        </tr>
                    `;
                    
                    tbody.append(row);
                });
            }

            // Format CNPJ for display
            function formatCNPJ(cnpj) {
                if (!cnpj || cnpj.length !== 14) return cnpj;
                
                return cnpj.substring(0, 2) + '.' + 
                       cnpj.substring(2, 5) + '.' + 
                       cnpj.substring(5, 8) + '/' + 
                       cnpj.substring(8, 12) + '-' + 
                       cnpj.substring(12, 14);
            }

            // Format Phone for display
            function formatPhone(ddd, telefone) {
                if (!ddd || !telefone) return '';
                
                const cleanPhone = String(telefone).replace(/\D/g, '');
                
                if (cleanPhone.length === 8) {
                    return `(${ddd}) ${cleanPhone.substring(0, 4)}-${cleanPhone.substring(4)}`;
                } else if (cleanPhone.length === 9) {
                    return `(${ddd}) ${cleanPhone.substring(0, 5)}-${cleanPhone.substring(5)}`;
                } else {
                    return `(${ddd}) ${cleanPhone}`;
                }
            }

            // Clear Form
            function clearForm() {
                $('#searchForm')[0].reset();
                $('.select2').val(null).trigger('change');
                $('#municipio').empty().append('<option value="">Selecione um estado primeiro...</option>').prop('disabled', true);
                $('#capital_entre').val('');
                $('#capital_inicial').val('');
                $('#capital_final').val('');
                hideResults();
                clearAlerts();
                hasValidResults = false;
                $('#exportBtn').prop('disabled', true);
            }

            // Show Alert
            function showAlert(message, type = 'info') {
                const alertHtml = `
                    <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                        ${message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                `;
                $('#alertContainer').html(alertHtml);
            }

            // Clear Alerts
            function clearAlerts() {
                $('#alertContainer').empty();
            }
        });
    </script>
</body>
</html>