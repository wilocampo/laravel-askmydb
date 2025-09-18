<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Ask My DB</title>
	<script src="https://cdn.tailwindcss.com"></script>
		<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
	<body class="bg-gray-50">
	<div class="min-h-screen p-6">
		<div class="w-full max-w-6xl mx-auto grid gap-6 md:grid-cols-2 items-start">
			<div class="bg-white shadow rounded-lg p-6">
			<h1 class="text-2xl font-semibold text-gray-800 mb-4">Ask My DB</h1>
			<p class="text-gray-600 mb-4">Query your database using natural language.</p>
			<form id="ask-form" class="space-y-4">
				<textarea id="prompt" name="prompt" rows="3" class="w-full border border-gray-300 rounded-md p-3 focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="e.g. list the 10 most recent users"></textarea>
				<div class="flex items-center gap-3">
					<button id="ask-btn" type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">Ask</button>
					<a href="{{ route('askmydb.schema') }}" target="_blank" class="text-sm text-gray-600 hover:text-gray-800">View schema JSON</a>
				</div>
			</form>
			<div id="alert" class="mt-4 hidden text-sm p-3 rounded"></div>
			<div id="response" class="mt-6 hidden">
				<h2 class="text-lg font-medium text-gray-800">Generated SQL</h2>
				<pre class="mt-2 bg-gray-100 p-3 rounded text-sm overflow-x-auto" id="sql"></pre>
				<h2 class="text-lg font-medium text-gray-800 mt-4">Result</h2>
				<pre class="mt-2 bg-gray-100 p-3 rounded text-sm overflow-x-auto max-h-80 overflow-y-auto" id="result"></pre>
			</div>
		</div>
			<div id="chart-card" class="bg-white shadow rounded-lg p-6 hidden">
				<h2 class="text-xl font-semibold text-gray-800">Chart</h2>
				<div id="chart-controls" class="mt-4">
					<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
						<div>
							<label class="block text-sm font-medium text-gray-700 mb-1">X Axis</label>
							<select id="x-col" class="w-full border border-gray-300 rounded-md p-2 focus:outline-none focus:ring-2 focus:ring-indigo-500"></select>
						</div>
						<div>
							<label class="block text-sm font-medium text-gray-700 mb-1">Y Axis (one or more)</label>
							<select id="y-cols" multiple class="w-full border border-gray-300 rounded-md p-2 focus:outline-none focus:ring-2 focus:ring-indigo-500 h-28"></select>
						</div>
						<div>
							<label class="block text-sm font-medium text-gray-700 mb-1">Chart Type</label>
							<select id="chart-type" class="w-full border border-gray-300 rounded-md p-2 focus:outline-none focus:ring-2 focus:ring-indigo-500">
								<option value="bar">Bar</option>
								<option value="line">Line</option>
								<option value="area">Area</option>
								<option value="pie">Pie</option>
								<option value="doughnut">Donut</option>
								<option value="scatter">Scatter</option>
							</select>
						</div>
						<div class="flex items-end">
							<button id="render-chart" type="button" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">Render chart</button>
						</div>
					</div>
				</div>
				<div class="mt-4">
					<canvas id="chart-canvas" height="260"></canvas>
				</div>
			</div>
		</div>
	</div>
	<script>
		const form = document.getElementById('ask-form');
		const button = document.getElementById('ask-btn');
		const alertBox = document.getElementById('alert');
		const resp = document.getElementById('response');
		const sql = document.getElementById('sql');
		const result = document.getElementById('result');

		function showAlert(message, type = 'error') {
			alertBox.textContent = message;
			alertBox.classList.remove('hidden');
			alertBox.className = 'mt-4 text-sm p-3 rounded ' + (type === 'error' ? 'bg-red-50 text-red-700 border border-red-200' : 'bg-green-50 text-green-700 border border-green-200');
		}

		form.addEventListener('submit', async (e) => {
			e.preventDefault();
			alertBox.classList.add('hidden');
			button.disabled = true;
			button.textContent = 'Asking...';
			try {
				const prompt = document.getElementById('prompt').value;
				const res = await fetch('{{ route('askmydb.ask') }}', {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-CSRF-TOKEN': '{{ csrf_token() }}',
					},
					body: JSON.stringify({ prompt })
				});
				if (!res.ok) {
					const text = await res.text();
					throw new Error('Request failed: ' + res.status + ' ' + text);
				}
				const data = await res.json();
				sql.textContent = data.sql ?? '';
				result.textContent = JSON.stringify(data.result, null, 2);
				resp.classList.remove('hidden');
				populateChartControls(data.result || []);
				try { renderChartFromRows(Array.isArray(data.result) ? data.result : []); } catch (_) {}
			} catch (err) {
				showAlert(err.message || 'Unknown error');
			} finally {
				button.disabled = false;
				button.textContent = 'Ask';
			}
		});

		let chartInstance = null;

		function populateChartControls(rows) {
			const controls = document.getElementById('chart-controls');
			const chartCard = document.getElementById('chart-card');
			const xSel = document.getElementById('x-col');
			const ySel = document.getElementById('y-cols');
			if (!Array.isArray(rows) || rows.length === 0) {
				chartCard.classList.add('hidden');
				controls.classList.add('hidden');
				return;
			}
			const cols = Object.keys(rows[0] || {});
			if (cols.length === 0) {
				chartCard.classList.add('hidden');
				controls.classList.add('hidden');
				return;
			}
			xSel.innerHTML = '';
			ySel.innerHTML = '';
			cols.forEach(c => {
				const opt1 = document.createElement('option');
				opt1.value = c; opt1.textContent = c; xSel.appendChild(opt1);
				const opt2 = document.createElement('option');
				opt2.value = c; opt2.textContent = c; ySel.appendChild(opt2);
			});
			// defaults
			xSel.value = cols[0] || '';
			const numericCols = cols.filter(c => rows.some(r => typeof r[c] === 'number' || (!isNaN(parseFloat(r[c])) && isFinite(r[c]))));
			for (const option of ySel.options) {
				if (numericCols.includes(option.value)) option.selected = true;
			}
			chartCard.classList.remove('hidden');
			controls.classList.remove('hidden');
		}

		function collectSelected(selectEl) {
			return Array.from(selectEl.options).filter(o => o.selected).map(o => o.value);
		}

		function renderChartFromRows(rows) {
			const xSel = document.getElementById('x-col');
			const ySel = document.getElementById('y-cols');
			const typeSel = document.getElementById('chart-type');
			const xKey = xSel.value;
			const yKeys = collectSelected(ySel);
			if (!xKey || yKeys.length === 0) {
				showAlert('Select X and Y axes to render chart.');
				return;
			}
			const labels = rows.map(r => String(r[xKey]));
			const palette = ['#6366F1','#10B981','#F59E0B','#EF4444','#3B82F6','#8B5CF6'];
			const ctx = document.getElementById('chart-canvas').getContext('2d');
			if (chartInstance) chartInstance.destroy();

			// Build datasets differently per chart family
			const chartType = typeSel.value;
			if (chartType === 'pie' || chartType === 'doughnut') {
				// Use only first Y series for pie/doughnut
				const y = yKeys[0];
				const data = rows.map(r => {
					const v = r[y];
					const n = typeof v === 'number' ? v : parseFloat(v);
					return isNaN(n) ? 0 : n;
				});
				const bg = labels.map((_, i) => palette[i % palette.length] + 'CC');
				chartInstance = new Chart(ctx, {
					type: chartType === 'pie' ? 'pie' : 'doughnut',
					data: { labels, datasets: [{ label: y, data, backgroundColor: bg, borderColor: '#fff' }] },
					options: { responsive: true, maintainAspectRatio: false }
				});
				return;
			}

			if (chartType === 'scatter') {
				// Use first two Y series: x->first Y, y->second Y
				if (yKeys.length < 2) {
					showAlert('Select at least two Y columns for scatter (X1, Y1).');
					return;
				}
				const [xSeries, ySeries] = yKeys;
				const points = rows.map(r => {
					const xv = r[xSeries];
					const yv = r[ySeries];
					const xnum = typeof xv === 'number' ? xv : parseFloat(xv);
					const ynum = typeof yv === 'number' ? yv : parseFloat(yv);
					return { x: isNaN(xnum) ? null : xnum, y: isNaN(ynum) ? null : ynum };
				}).filter(p => p.x !== null && p.y !== null);
				chartInstance = new Chart(ctx, {
					type: 'scatter',
					data: { datasets: [{ label: `${xSeries} vs ${ySeries}`, data: points, backgroundColor: '#3B82F6' }] },
					options: { responsive: true, maintainAspectRatio: false }
				});
				return;
			}

			// bar, line, area (line with fill)
			const datasets = yKeys.map((yk, idx) => {
				const data = rows.map(r => {
					const v = r[yk];
					const n = typeof v === 'number' ? v : parseFloat(v);
					return isNaN(n) ? null : n;
				});
				const color = palette[idx % palette.length];
				return { label: yk, data, borderColor: color, backgroundColor: color + '80', fill: (chartType === 'area'), tension: 0.3 };
			});
			const baseType = chartType === 'area' ? 'line' : chartType; // Chart.js area via line+fill
			chartInstance = new Chart(ctx, {
				type: baseType,
				data: { labels, datasets },
				options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } } }
			});
		}

		document.getElementById('render-chart').addEventListener('click', () => {
			try {
				const rows = JSON.parse(result.textContent || '[]');
				renderChartFromRows(rows);
			} catch (e) {
				showAlert('Cannot render chart: invalid data');
			}
		});

		// Auto-render on axis/type changes
		['x-col','y-cols','chart-type'].forEach(id => {
			const el = document.getElementById(id);
			el.addEventListener('change', () => {
				try {
					const rows = JSON.parse(result.textContent || '[]');
					renderChartFromRows(rows);
				} catch (_) {}
			});
		});
	</script>
</body>
</html>
