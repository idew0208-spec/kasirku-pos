'use strict';
const $ = (selector, root=document) => root.querySelector(selector);
const $$ = (selector, root=document) => [...root.querySelectorAll(selector)];
const state = {products:[],categories:[],cart:new Map(),category:'Semua',query:'',discountType:'none',discountInput:0,method:'cash',paid:0,lastSale:null,inventory:[],history:[]};
const money = value => 'Rp' + Math.max(0,Math.round(Number(value)||0)).toLocaleString('id-ID');
const esc = value => String(value ?? '').replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch]));
const icons = {'Sembako':'▧','Makanan':'▤','Minuman':'◉','Perawatan':'✦'};
async function api(action, options={}) {
  const response = await fetch(`api.php?action=${encodeURIComponent(action)}`, {headers:{'Content-Type':'application/json'},...options});
  let data;
  try { data = await response.json(); } catch { throw new Error('Respons server tidak valid. Pastikan PHP dan MySQL berjalan.'); }
  if (!response.ok || !data.ok) throw new Error(data.error || 'Permintaan gagal.');
  return data;
}
function notify(message) {
  const el=$('#toast'); el.textContent=message; el.classList.add('show');
  clearTimeout(notify.timer); notify.timer=setTimeout(()=>el.classList.remove('show'),3000);
}
function totals() {
  let subtotal=0;
  for(const {product,quantity} of state.cart.values()) subtotal += Number(product.price)*quantity;
  const tax=Math.floor((subtotal*11+50)/100);
  let discount=0;
  const input=Math.max(0,Math.floor(Number(state.discountInput)||0));
  if(state.discountType==='amount') discount=Math.min(input,subtotal);
  if(state.discountType==='percent') discount=Math.min(subtotal,Math.floor((subtotal*Math.min(100,input)+50)/100));
  return {subtotal,tax,discount,grandTotal:Math.max(0,subtotal+tax-discount)};
}
function renderProducts() {
  const grid=$('#productGrid');
  $('#productCount').textContent=`${state.products.length} produk ditemukan`;
  if(!state.products.length){grid.innerHTML='<div class="loading">Produk tidak ditemukan. Coba kata kunci lain.</div>';return;}
  grid.innerHTML=state.products.map(p=>{
    const low=Number(p.stock)<5, stock=Number(p.stock);
    const img=p.image_url ? `<img src="${esc(p.image_url)}" alt="${esc(p.name)}" loading="lazy" onerror="this.outerHTML='<div class=&quot;product-placeholder&quot;>${icons[p.category]||'▧'}</div>'">` : `<div class="product-placeholder">${icons[p.category]||'▧'}</div>`;
    return `<article class="product-card" data-product="${p.id}" tabindex="0" role="button" aria-label="Tambah ${esc(p.name)} ke keranjang"><div class="product-image">${img}<span class="stock-tag ${low?'low':''}">${stock===0?'Stok habis':low?'Stok '+stock:'Stok '+stock}</span></div><div class="product-info"><div class="product-category">${esc(p.category)} · ${esc(p.sku)}</div><p class="product-name">${esc(p.name)}</p><div class="product-bottom"><span class="product-price">${money(p.price)}</span><button class="add-product" type="button" aria-label="Tambah produk" ${stock<1?'disabled':''}>+</button></div></div></article>`;
  }).join('');
}
async function loadProducts() {
  const params=new URLSearchParams({action:'products',q:state.query,category:state.category});
  const response=await fetch(`api.php?${params.toString()}`);
  const data=await response.json();
  if(!response.ok || !data.ok) throw new Error(data.error || 'Gagal memuat produk.');
  state.products=data.products;renderProducts();
}
async function loadCategories() {
  const data=await api('categories');state.categories=data.categories;
  $('#categoryTabs').innerHTML=['Semua',...state.categories].map(c=>`<button class="category ${c===state.category?'active':''}" data-category="${esc(c)}">${esc(c==='Semua'?'Semua produk':c)}</button>`).join('');
}
function renderCart() {
  const container=$('#cartItems');let count=0;
  for(const row of state.cart.values())count+=row.quantity;
  $('#cartCount').textContent=count;
  if(!state.cart.size){container.innerHTML='<div class="empty-cart"><div class="empty-icon">＋</div><strong>Keranjang masih kosong</strong><p>Klik produk untuk mulai transaksi.</p></div>';}
  else container.innerHTML=[...state.cart.values()].map(({product:p,quantity})=>`<div class="cart-item"><div><div class="cart-item-name">${esc(p.name)}</div><div class="cart-item-price">${money(p.price)} × ${quantity}<br><strong>${money(Number(p.price)*quantity)}</strong></div></div><div class="quantity-control"><button data-qty="-1" data-id="${p.id}" aria-label="Kurangi">−</button><span>${quantity}</span><button data-qty="1" data-id="${p.id}" aria-label="Tambah" ${quantity>=Number(p.stock)?'disabled':''}>+</button><button data-remove="${p.id}" aria-label="Hapus" title="Hapus item" style="color:#d05c52;border-color:transparent">×</button></div></div>`).join('');
  const t=totals();
  $('#subtotal').textContent=money(t.subtotal);$('#tax').textContent=money(t.tax);$('#discountAmount').textContent='− '+money(t.discount);$('#grandTotal').textContent=money(t.grandTotal);
  $('#openPayment').disabled=!state.cart.size;
  $('#discountInput').disabled=state.discountType==='none';
}
function addToCart(id) {
  const p=state.products.find(p=>Number(p.id)===Number(id));
  if(!p)return;
  if(Number(p.stock)<1){notify('Stok produk habis.');return;}
  const existing=state.cart.get(Number(id));
  if(existing && existing.quantity>=Number(p.stock)){notify('Jumlah melebihi stok yang tersedia.');return;}
  state.cart.set(Number(id),{product:p,quantity:(existing?.quantity||0)+1});renderCart();
}
function changeQty(id,delta) {
  const row=state.cart.get(Number(id));if(!row)return;
  const next=row.quantity+delta;
  if(next<1)state.cart.delete(Number(id));
  else if(next>Number(row.product.stock)){notify('Stok tidak mencukupi.');return;}
  else row.quantity=next;
  renderCart();
}
function showPage(name) {
  $$('.page').forEach(p=>p.classList.toggle('active',p.id===`page-${name}`));
  $$('.nav-item').forEach(b=>b.classList.toggle('active',b.dataset.page===name));
  $('#pageTitle').textContent=({kasir:'Kasir',inventaris:'Inventaris',riwayat:'Riwayat transaksi',laporan:'Laporan'})[name]||'Kasir';
  $('#sidebar').classList.remove('open');
  if(name==='inventaris')loadInventory();if(name==='riwayat')loadHistory();if(name==='laporan')loadReport();
}
function methodName(method){return ({cash:'Tunai',qris:'QRIS (Simulasi)',transfer_card:'Transfer / Kartu (Simulasi)'})[method]||method;}
function paymentUI() {
  const t=totals();$('#paymentTotal').textContent=money(t.grandTotal);$('#noncashTotal').textContent=money(t.grandTotal);
  $$('.payment-method').forEach(b=>b.classList.toggle('active',b.dataset.method===state.method));
  $('#cashSection').classList.toggle('hidden',state.method!=='cash');$('#noncashSection').classList.toggle('hidden',state.method==='cash');
  $('#simulationNote').textContent=state.method==='cash'?'Pembayaran tunai divalidasi berdasarkan nominal uang yang diterima.':'Mode demo: pembayaran non-tunai belum terhubung ke payment gateway.';
  $('#paymentError').textContent='';
  if(state.method==='cash')updateChange();
  $('#confirmPayment').disabled=state.method==='cash' && Math.floor(Number($('#paidInput').value)||0)<t.grandTotal;
}
function updateChange() {
  const total=totals().grandTotal, paid=Math.max(0,Math.floor(Number($('#paidInput').value)||0));
  const valid=paid>=total;
  $('#changeValue').textContent=money(valid?paid-total:0);
  $('#paymentError').textContent=paid>0&&!valid?'Uang yang diterima masih kurang.': '';
  $('#confirmPayment').disabled=!valid||!state.cart.size;
}
function openPayment() {
  if(!state.cart.size)return;
  state.method='cash';$('#paidInput').value='';$('#paymentModal').classList.remove('hidden');document.body.style.overflow='hidden';paymentUI();
}
function closePayment(){ $('#paymentModal').classList.add('hidden');if($('#receiptModal').classList.contains('hidden'))document.body.style.overflow=''; }
async function confirmPayment() {
  const t=totals(), paid=Math.max(0,Math.floor(Number($('#paidInput').value)||0));
  if(state.method==='cash'&&paid<t.grandTotal){$('#paymentError').textContent='Uang yang diterima kurang dari total belanja.';return;}
  const payload={items:[...state.cart.values()].map(r=>({id:Number(r.product.id),quantity:r.quantity})),discount_type:state.discountType,discount_input:Math.max(0,Math.floor(Number(state.discountInput)||0)),payment_method:state.method,paid_amount:state.method==='cash'?paid:t.grandTotal};
  const button=$('#confirmPayment');button.disabled=true;button.innerHTML='Memproses…';
  try {
    const sale=await api('checkout',{method:'POST',body:JSON.stringify(payload)});
    state.lastSale=sale;state.cart.clear();state.discountType='none';state.discountInput=0;$('#discountType').value='none';$('#discountInput').value='0';
    closePayment();renderCart();showReceipt(sale);await Promise.all([loadProducts(),loadInventory(),loadHistory(),loadReport()]);
  } catch(err){$('#paymentError').textContent=err.message;notify(err.message);paymentUI();}
  finally{button.innerHTML='Konfirmasi bayar <span>→</span>';paymentUI();}
}
function receiptHTML(sale) {
  const items=sale.items||[];
  return `<div class="receipt-paper"><div class="receipt-store"><strong>TOKO KELONTONG</strong><span>Terima kasih telah berbelanja</span><br><span>${esc(sale.invoice_no||'')}</span><br><span>${esc(sale.created_at||'')}</span></div>${items.map(it=>`<div class="receipt-item"><div class="receipt-item-top"><span>${esc(it.name)}</span><span>${money(it.line_total)}</span></div><small>${it.quantity} × ${money(it.unit_price)}</small></div>`).join('')}<div class="receipt-rule"></div><div class="receipt-line"><span>Subtotal</span><strong>${money(sale.subtotal)}</strong></div><div class="receipt-line"><span>PPN 11%</span><strong>${money(sale.tax)}</strong></div><div class="receipt-line"><span>Diskon</span><strong>− ${money(sale.discount_amount)}</strong></div><div class="receipt-rule"></div><div class="receipt-line"><strong>TOTAL</strong><strong>${money(sale.grand_total)}</strong></div><div class="receipt-line"><span>Metode</span><span>${esc(methodName(sale.payment_method))}</span></div><div class="receipt-line"><span>Dibayar</span><span>${money(sale.paid_amount)}</span></div><div class="receipt-line"><span>Kembalian</span><span>${money(sale.change_amount)}</span></div><div class="receipt-thanks">Simpan struk ini sebagai bukti transaksi.<br>Semoga hari Anda menyenangkan!</div></div>`;
}
function showReceipt(sale) {
  $('#receiptContent').innerHTML=receiptHTML(sale);$('#receiptModal').classList.remove('hidden');document.body.style.overflow='hidden';
}
function closeReceipt(){ $('#receiptModal').classList.add('hidden');document.body.style.overflow='';showPage('kasir');}
function printSale(sale) {
  $('#printArea').innerHTML=receiptHTML(sale);window.print();setTimeout(()=>$('#printArea').innerHTML='',500);
}
async function loadInventory() {
  const data=await api('inventory');state.inventory=data.products;
  const low=state.inventory.filter(p=>Number(p.stock)<5).length;
  $('#lowCount').textContent=low;$('#invTotal').textContent=state.inventory.length;$('#invLow').textContent=low;$('#invUnits').textContent=state.inventory.reduce((s,p)=>s+Number(p.stock),0);
  renderInventory();
}
function renderInventory() {
  const q=($('#inventorySearch').value||'').toLowerCase();
  const rows=state.inventory.filter(p=>[p.name,p.sku,p.category].some(x=>String(x).toLowerCase().includes(q)));
  $('#inventoryBody').innerHTML=rows.length?rows.map(p=>{const stock=Number(p.stock),status=stock<5?'<span class="status-tag critical">Stok kritis</span>':stock<10?'<span class="status-tag warning">Perlu dipantau</span>':'<span class="status-tag">Tersedia</span>';return `<tr><td><div class="table-product"><span class="table-product-avatar">${icons[p.category]||'▧'}</span><div><strong>${esc(p.name)}</strong><div style="color:#9aa49e;font-size:9px;margin-top:4px">${esc(p.sku)}</div></div></div></td><td>${esc(p.category)}</td><td><strong>${money(p.price)}</strong></td><td><strong class="${stock<5?'red-text':''}">${stock} pcs</strong></td><td>${status}</td></tr>`;}).join(''):'<tr><td colspan="5">Produk tidak ditemukan.</td></tr>';
}
async function loadHistory() {
  const data=await api('history');state.history=data.sales;renderHistory();
}
function renderHistory() {
  const q=($('#historySearch').value||'').toLowerCase();
  const rows=state.history.filter(s=>String(s.invoice_no).toLowerCase().includes(q));
  $('#historyBody').innerHTML=rows.length?rows.map(s=>`<tr><td><strong>${esc(s.invoice_no)}</strong><div style="font-size:9px;color:#98a39c;margin-top:5px">${esc(s.created_at)}</div></td><td>${Number(s.item_count)} unit · ${Number(s.item_types)} jenis</td><td><span class="status-tag">${esc(methodName(s.payment_method))}</span></td><td><strong>${money(s.grand_total)}</strong></td><td><button class="table-action" data-reprint="${s.id}">Lihat struk</button></td></tr>`).join(''):'<tr><td colspan="5">Belum ada transaksi yang cocok.</td></tr>';
}
async function reprint(id) {
  const response=await fetch(`api.php?action=sale_detail&id=${encodeURIComponent(id)}`);
  const data=await response.json();
  if(!response.ok || !data.ok) throw new Error(data.error || 'Gagal mengambil struk transaksi.');
  const sale=data.sale;sale.items=data.items;
  $('#receiptContent').innerHTML=receiptHTML(sale);$('#receiptModal').classList.remove('hidden');document.body.style.overflow='hidden';
  state.lastSale=sale;
}
async function loadReport() {
  const data=await api('report');
  $('#reportToday').textContent=money(Number(data.today.revenue));$('#reportTodayCount').textContent=`${data.today.transactions} transaksi hari ini`;
  $('#reportMonth').textContent=money(Number(data.month.revenue));$('#reportMonthCount').textContent=`${data.month.transactions} transaksi bulan ini`;
  $('#reportLow').textContent=data.low_stock;$('#lowCount').textContent=data.low_stock;
}
function debounce(fn,delay=250){let timer;return(...args)=>{clearTimeout(timer);timer=setTimeout(()=>fn(...args),delay);};}
async function refreshCatalog(){try{await loadProducts();}catch(e){$('#productGrid').innerHTML=`<div class="loading">${esc(e.message)}<br><br>Pastikan database sudah diimpor dan kredensial config.php benar.</div>`;}}
function init() {
  const now=new Date();$('#todayLabel').textContent=now.toLocaleDateString('id-ID',{weekday:'long',day:'numeric',month:'long',year:'numeric'}).toUpperCase();$('#dateChip').textContent=now.toLocaleDateString('id-ID',{day:'numeric',month:'short',year:'numeric'});
  refreshCatalog();loadCategories().then(()=>refreshCatalog()).catch(e=>notify(e.message));
  $$('.nav-item').forEach(b=>b.addEventListener('click',()=>showPage(b.dataset.page)));
  $('#mobileMenu').addEventListener('click',()=>$('#sidebar').classList.toggle('open'));
  $('#searchInput').addEventListener('input',debounce(async e=>{state.query=e.target.value;try{await loadProducts();}catch(err){notify(err.message);}},250));
  $('#refreshProducts').addEventListener('click',refreshCatalog);
  $('#categoryTabs').addEventListener('click',async e=>{const b=e.target.closest('[data-category]');if(!b)return;state.category=b.dataset.category;$$('.category').forEach(c=>c.classList.toggle('active',c===b));try{await loadProducts();}catch(err){notify(err.message);}});
  $('#productGrid').addEventListener('click',e=>{const card=e.target.closest('[data-product]');if(card)addToCart(card.dataset.product);});
  $('#productGrid').addEventListener('keydown',e=>{if((e.key==='Enter'||e.key===' ')&&e.target.matches('[data-product]')){e.preventDefault();addToCart(e.target.dataset.product);}});
  $('#cartItems').addEventListener('click',e=>{const qty=e.target.closest('[data-qty]'),remove=e.target.closest('[data-remove]');if(qty)changeQty(qty.dataset.id,Number(qty.dataset.qty));if(remove){state.cart.delete(Number(remove.dataset.remove));renderCart();}});
  $('#clearCart').addEventListener('click',()=>{state.cart.clear();renderCart();});
  $('#discountType').addEventListener('change',e=>{state.discountType=e.target.value;renderCart();});
  $('#discountInput').addEventListener('input',e=>{state.discountInput=Math.max(0,Math.floor(Number(e.target.value)||0));if(state.discountType==='percent'&&state.discountInput>100){state.discountInput=100;e.target.value='100';}renderCart();});
  $('#openPayment').addEventListener('click',openPayment);$('#closePayment').addEventListener('click',closePayment);$('#cancelPayment').addEventListener('click',closePayment);
  $$('.payment-method').forEach(b=>b.addEventListener('click',()=>{state.method=b.dataset.method;paymentUI();}));
  $('#paidInput').addEventListener('input',updateChange);
  $$('.quick-money button').forEach(b=>b.addEventListener('click',()=>{$('#paidInput').value=b.dataset.cash==='exact'?totals().grandTotal:b.dataset.cash;updateChange();}));
  $('#confirmPayment').addEventListener('click',confirmPayment);
  $('#closeReceipt').addEventListener('click',closeReceipt);
  $('#printReceipt').addEventListener('click',()=>{if(state.lastSale)printSale(state.lastSale);});
  $('#refreshInventory').addEventListener('click',()=>loadInventory().catch(e=>notify(e.message)));
  $('#inventorySearch').addEventListener('input',renderInventory);
  $('#refreshHistory').addEventListener('click',()=>loadHistory().catch(e=>notify(e.message)));
  $('#historySearch').addEventListener('input',renderHistory);
  $('#refreshReport').addEventListener('click',()=>loadReport().catch(e=>notify(e.message)));
  $('#historyBody').addEventListener('click',e=>{const b=e.target.closest('[data-reprint]');if(b)reprint(b.dataset.reprint).catch(err=>notify(err.message));});
  for(const id of ['paymentModal','receiptModal'])$('#'+id).addEventListener('click',e=>{if(e.target.id===id){if(id==='paymentModal')closePayment();else closeReceipt();}});
  document.addEventListener('keydown',e=>{if(e.key==='Escape'){closePayment();closeReceipt();}if(e.key==='/'&&!['INPUT','TEXTAREA'].includes(document.activeElement.tagName)){e.preventDefault();$('#searchInput').focus();}});
}
document.addEventListener('DOMContentLoaded',init);
