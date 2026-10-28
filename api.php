<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
session_start();
header('Cache-Control: no-store');
$action = $_GET['action'] ?? '';

try {
    if ($action === 'auth_login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $username = trim((string)($data['username'] ?? ''));
        $password = (string)($data['password'] ?? '');
        if ($username === '' || $password === '') json_response(['ok'=>false,'error'=>'Username dan password wajib diisi.'],400);
        try {
            $stmt = db()->prepare('SELECT id, username, password_hash, display_name, role, active FROM users WHERE username=? LIMIT 1');
            $stmt->execute([$username]);
            $user = $stmt->fetch();
        } catch (PDOException $e) {
            json_response(['ok'=>false,'error'=>'Tabel pengguna belum tersedia. Impor upgrade.sql terlebih dahulu.'],400);
        }
        if (!$user || !(int)$user['active'] || !password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);

                $_SESSION['user'] = [
                    'id'           => (int) $user['id'],
                    'username'     => $user['username'],
                    'display_name' => $user['display_name'] ?? $user['username'],
                    'role'         => $user['role'],
                ];

        }
        session_regenerate_id(true);
        $_SESSION['user'] = ['id'=>(int)$user['id'], 'username'=>$user['username'], 'display_name'=>$user['display_name'], 'role'=>$user['role']];
        json_response(['ok'=>true,'user'=>$_SESSION['user']]);
    }
    if ($action === 'auth_logout' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time()-42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
        json_response(['ok'=>true]);
    }
    if ($action === 'auth_me') {
        json_response(['ok'=>true,'user'=>$_SESSION['user'] ?? null]);
    }
    if (empty($_SESSION['user'])) {
        json_response(['ok'=>false,'error'=>'Sesi login berakhir. Silakan login kembali.','auth_required'=>true],401);
    }

    if ($action === 'products') {
        $q = trim((string)($_GET['q'] ?? ''));
        $category = trim((string)($_GET['category'] ?? ''));
        $sql = 'SELECT id,sku,name,category,price,stock,image_url FROM products WHERE 1=1';
        $params = [];
        if ($q !== '') {
            $sql .= ' AND (name LIKE ? OR sku LIKE ? OR category LIKE ?)';
            $needle = '%' . $q . '%';
            array_push($params, $needle, $needle, $needle);
        }
        if ($category !== '' && $category !== 'Semua') {
            $sql .= ' AND category = ?';
            $params[] = $category;
        }
        $sql .= ' ORDER BY name ASC';
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        json_response(['ok'=>true,'products'=>$stmt->fetchAll()]);
    }

    if ($action === 'categories') {
        $rows = db()->query('SELECT DISTINCT category FROM products ORDER BY category')->fetchAll(PDO::FETCH_COLUMN);
        json_response(['ok'=>true,'categories'=>$rows]);
    }

    if ($action === 'inventory') {
        $rows = db()->query('SELECT id,sku,name,category,price,stock,updated_at FROM products ORDER BY name')->fetchAll();
        json_response(['ok'=>true,'products'=>$rows]);
    }

    if ($action === 'history') {
        $stmt = db()->query("SELECT s.id,s.invoice_no,s.subtotal,s.tax,s.discount_amount,s.grand_total,s.payment_method,s.paid_amount,s.change_amount,s.created_at,
            (SELECT COUNT(*) FROM sale_items si WHERE si.sale_id=s.id) AS item_types,
            (SELECT COALESCE(SUM(quantity),0) FROM sale_items si WHERE si.sale_id=s.id) AS item_count
            FROM sales s ORDER BY s.id DESC LIMIT 250");
        json_response(['ok'=>true,'sales'=>$stmt->fetchAll()]);
    }

    if ($action === 'report') {
        $today = db()->query("SELECT COUNT(*) transactions, COALESCE(SUM(grand_total),0) revenue FROM sales WHERE DATE(created_at)=CURDATE()")->fetch();
        $month = db()->query("SELECT COUNT(*) transactions, COALESCE(SUM(grand_total),0) revenue FROM sales WHERE YEAR(created_at)=YEAR(CURDATE()) AND MONTH(created_at)=MONTH(CURDATE())")->fetch();
        $low = db()->query("SELECT COUNT(*) FROM products WHERE stock < 5")->fetchColumn();
        json_response(['ok'=>true,'today'=>$today,'month'=>$month,'low_stock'=>(int)$low]);
    }

    if ($action === 'sale_detail') {
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        if (!$id || $id < 1) json_response(['ok'=>false,'error'=>'ID transaksi tidak valid.'],400);
        $stmt = db()->prepare('SELECT * FROM sales WHERE id=?');
        $stmt->execute([$id]);
        $sale = $stmt->fetch();
        if (!$sale) json_response(['ok'=>false,'error'=>'Transaksi tidak ditemukan.'],404);
        $stmt = db()->prepare('SELECT product_name,sku,quantity,unit_price,line_total FROM sale_items WHERE sale_id=? ORDER BY id');
        $stmt->execute([$id]);
        json_response(['ok'=>true,'sale'=>$sale,'items'=>$stmt->fetchAll()]);
    }

    if ($action === 'checkout' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);
        if (!is_array($data)) json_response(['ok'=>false,'error'=>'Data transaksi tidak valid.'],400);

        $items = $data['items'] ?? [];
        $method = $data['payment_method'] ?? '';
        $discountType = $data['discount_type'] ?? 'none';
        $discountInput = filter_var($data['discount_input'] ?? 0, FILTER_VALIDATE_INT);
        $paidAmount = filter_var($data['paid_amount'] ?? 0, FILTER_VALIDATE_INT);
        if (!is_array($items) || count($items) < 1) json_response(['ok'=>false,'error'=>'Keranjang masih kosong.'],400);
        if (!in_array($method, ['cash','qris','transfer_card'], true)) json_response(['ok'=>false,'error'=>'Metode pembayaran tidak valid.'],400);
        if (!in_array($discountType, ['none','amount','percent'], true)) json_response(['ok'=>false,'error'=>'Jenis diskon tidak valid.'],400);
        if ($discountInput === false || $discountInput < 0 || $paidAmount === false || $paidAmount < 0) json_response(['ok'=>false,'error'=>'Nominal diskon atau pembayaran tidak valid.'],400);
        if ($discountType === 'percent' && $discountInput > 100) json_response(['ok'=>false,'error'=>'Diskon persentase maksimal 100%.'],400);

        // Normalize duplicate product IDs and validate integer quantities.
        $quantities = [];
        foreach ($items as $item) {
            $id = filter_var($item['id'] ?? null, FILTER_VALIDATE_INT);
            $qty = filter_var($item['quantity'] ?? null, FILTER_VALIDATE_INT);
            if (!$id || $id < 1 || $qty === false || $qty < 1 || $qty > 10000) json_response(['ok'=>false,'error'=>'Produk atau jumlah barang tidak valid.'],400);
            $quantities[$id] = ($quantities[$id] ?? 0) + $qty;
        }

        $pdo = db();
        $pdo->beginTransaction();
        try {
            $ids = array_keys($quantities);
            sort($ids, SORT_NUMERIC); // Consistent lock order reduces deadlocks.
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $pdo->prepare("SELECT id,sku,name,category,price,stock FROM products WHERE id IN ($placeholders) ORDER BY id FOR UPDATE");
            $stmt->execute($ids);
            $found = $stmt->fetchAll();
            if (count($found) !== count($ids)) throw new RuntimeException('Ada produk yang sudah tidak tersedia.');
            $byId = [];
            foreach ($found as $p) $byId[(int)$p['id']] = $p;

            $subtotal = 0;
            $lineItems = [];
            foreach ($quantities as $id => $qty) {
                $p = $byId[$id];
                if ((int)$p['stock'] < $qty) throw new RuntimeException('Stok ' . $p['name'] . ' tidak cukup. Tersedia ' . $p['stock'] . '.');
                $unit = (int)$p['price'];
                if ($unit > intdiv(PHP_INT_MAX, $qty)) throw new RuntimeException('Nilai transaksi terlalu besar.');
                $line = $unit * $qty;
                if ($subtotal > PHP_INT_MAX - $line) throw new RuntimeException('Nilai transaksi terlalu besar.');
                $subtotal += $line;
                $lineItems[] = ['product'=>$p,'quantity'=>$qty,'unit_price'=>$unit,'line_total'=>$line];
            }

            $tax = intdiv($subtotal * 11 + 50, 100); // PPN 11%, rounded to nearest whole rupiah.
            $discount = match ($discountType) {
                'amount' => min($discountInput, $subtotal),
                'percent' => intdiv($subtotal * $discountInput + 50, 100),
                default => 0,
            };
            $grandTotal = max(0, $subtotal + $tax - $discount);
            if ($method === 'cash') {
                if ($paidAmount < $grandTotal) throw new RuntimeException('Uang tunai kurang dari total belanja.');
                $change = $paidAmount - $grandTotal;
            } else {
                // QRIS/card/transfer are simulated: exact total is treated as confirmed payment.
                $paidAmount = $grandTotal;
                $change = 0;
            }

            $invoice = 'POS-' . date('Ymd-His') . '-' . strtoupper(bin2hex(random_bytes(3)));
            $stmt = $pdo->prepare('INSERT INTO sales (invoice_no,subtotal,tax,discount_type,discount_input,discount_amount,grand_total,payment_method,paid_amount,change_amount) VALUES (?,?,?,?,?,?,?,?,?,?)');
            $stmt->execute([$invoice,$subtotal,$tax,$discountType,$discountInput,$discount,$grandTotal,$method,$paidAmount,$change]);
            $saleId = (int)$pdo->lastInsertId();

            $insertItem = $pdo->prepare('INSERT INTO sale_items (sale_id,product_id,product_name,sku,quantity,unit_price,line_total) VALUES (?,?,?,?,?,?,?)');
            $updateStock = $pdo->prepare('UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?');
            foreach ($lineItems as $li) {
                $p = $li['product'];
                $insertItem->execute([$saleId,$p['id'],$p['name'],$p['sku'],$li['quantity'],$li['unit_price'],$li['line_total']]);
                $updateStock->execute([$li['quantity'],$p['id'],$li['quantity']]);
                if ($updateStock->rowCount() !== 1) throw new RuntimeException('Stok berubah saat checkout. Silakan ulangi transaksi.');
            }
            $pdo->commit();
            json_response(['ok'=>true,'message'=>'Pembayaran berhasil.','sale_id'=>$saleId,'invoice_no'=>$invoice,'subtotal'=>$subtotal,'tax'=>$tax,'discount_amount'=>$discount,'grand_total'=>$grandTotal,'paid_amount'=>$paidAmount,'change_amount'=>$change,'payment_method'=>$method,'created_at'=>date('Y-m-d H:i:s'),'items'=>array_map(fn($li)=>['name'=>$li['product']['name'],'sku'=>$li['product']['sku'],'quantity'=>$li['quantity'],'unit_price'=>$li['unit_price'],'line_total'=>$li['line_total']],$lineItems)]);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $d=json_decode(file_get_contents('php://input'),true) ?: [];
        $st=db()->prepare('SELECT id,username,password_hash,display_name,role FROM users WHERE username=? AND active=1');
        $st->execute([trim((string)($d['username']??''))]); $u=$st->fetch();
        if(!$u || !password_verify((string)($d['password']??''),$u['password_hash'])) json_response(['ok'=>false,'error'=>'Username atau password salah.'],401);
        session_regenerate_id(true); $_SESSION['user']=['id'=>$u['id'],'username'=>$u['username'],'name'=>$u['display_name'],'role'=>$u['role']]; unset($u['password_hash']);
        json_response(['ok'=>true,'user'=>$_SESSION['user']]);
    }
    if ($action === 'logout') { $_SESSION=[]; session_destroy(); json_response(['ok'=>true]); }
    if ($action === 'session') json_response(['ok'=>true,'user'=>$_SESSION['user']??null]);
    if ($action === 'customers') {
        if($_SERVER['REQUEST_METHOD']==='GET') json_response(['ok'=>true,'customers'=>db()->query('SELECT c.*, (SELECT COUNT(*) FROM sales s WHERE s.customer_id=c.id) transactions FROM customers c ORDER BY c.name')->fetchAll()]);
        $d=json_decode(file_get_contents('php://input'),true)?:[]; $name=trim((string)($d['name']??''));
        if($name==='') json_response(['ok'=>false,'error'=>'Nama pelanggan wajib diisi.'],400);
        $st=db()->prepare('INSERT INTO customers(name,phone,notes) VALUES(?,?,?)');$st->execute([$name,trim((string)($d['phone']??''))?:null,trim((string)($d['notes']??''))?:null]);json_response(['ok'=>true,'id'=>db()->lastInsertId()]);
    }
    if ($action === 'suppliers') {
        if($_SERVER['REQUEST_METHOD']==='GET') json_response(['ok'=>true,'suppliers'=>db()->query('SELECT * FROM suppliers ORDER BY name')->fetchAll()]);
        $d=json_decode(file_get_contents('php://input'),true)?:[];$name=trim((string)($d['name']??''));if($name==='')json_response(['ok'=>false,'error'=>'Nama pemasok wajib diisi.'],400);
        $st=db()->prepare('INSERT INTO suppliers(name,phone,address) VALUES(?,?,?)');$st->execute([$name,trim((string)($d['phone']??''))?:null,trim((string)($d['address']??''))?:null]);json_response(['ok'=>true,'id'=>db()->lastInsertId()]);
    }
    if ($action === 'purchase' && $_SERVER['REQUEST_METHOD']==='POST') {
        $d=json_decode(file_get_contents('php://input'),true)?:[];$items=$d['items']??[];if(!is_array($items)||!count($items))json_response(['ok'=>false,'error'=>'Tambahkan minimal satu barang.'],400);
        $pdo=db();$pdo->beginTransaction();try{$ref='BELI-'.date('Ymd-His').'-'.strtoupper(bin2hex(random_bytes(2)));$total=0;$valid=[];
        foreach($items as $it){$id=filter_var($it['product_id']??0,FILTER_VALIDATE_INT);$qty=filter_var($it['quantity']??0,FILTER_VALIDATE_INT);$cost=filter_var($it['unit_cost']??0,FILTER_VALIDATE_INT);if(!$id||!$qty||$qty<1||$cost===false||$cost<0)throw new RuntimeException('Data item pembelian tidak valid.');$st=$pdo->prepare('SELECT id FROM products WHERE id=? FOR UPDATE');$st->execute([$id]);if(!$st->fetch())throw new RuntimeException('Produk tidak ditemukan.');$line=$qty*$cost;$total+=$line;$valid[]=[$id,$qty,$cost,$line];}
        $st=$pdo->prepare('INSERT INTO purchases(supplier_id,reference_no,total_cost,notes) VALUES(?,?,?,?)');$sid=filter_var($d['supplier_id']??null,FILTER_VALIDATE_INT);$st->execute([$sid?:null,$ref,$total,trim((string)($d['notes']??''))?:null]);$pid=(int)$pdo->lastInsertId();$ins=$pdo->prepare('INSERT INTO purchase_items(purchase_id,product_id,quantity,unit_cost,line_total) VALUES(?,?,?,?,?)');$upd=$pdo->prepare('UPDATE products SET stock=stock+? WHERE id=?');foreach($valid as $v){$ins->execute([$pid,...$v]);$upd->execute([$v[1],$v[0]]);} $pdo->commit();json_response(['ok'=>true,'reference_no'=>$ref,'total_cost'=>$total]);}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }
    if ($action === 'expenses') {
        if($_SERVER['REQUEST_METHOD']==='GET')json_response(['ok'=>true,'expenses'=>db()->query('SELECT * FROM expenses ORDER BY id DESC LIMIT 500')->fetchAll()]);
        $d=json_decode(file_get_contents('php://input'),true)?:[];$desc=trim((string)($d['description']??''));$amount=filter_var($d['amount']??null,FILTER_VALIDATE_INT);if($desc===''||$amount===false||$amount<1)json_response(['ok'=>false,'error'=>'Deskripsi dan nominal pengeluaran wajib valid.'],400);$st=db()->prepare('INSERT INTO expenses(description,amount,category) VALUES(?,?,?)');$st->execute([$desc,$amount,trim((string)($d['category']??'Operasional'))]);json_response(['ok'=>true]);
    }
    if ($action === 'cash') {
        if($_SERVER['REQUEST_METHOD']==='GET')json_response(['ok'=>true,'movements'=>db()->query('SELECT * FROM cash_movements ORDER BY id DESC LIMIT 500')->fetchAll()]);
        $d=json_decode(file_get_contents('php://input'),true)?:[];$type=$d['movement_type']??'in';$amount=filter_var($d['amount']??null,FILTER_VALIDATE_INT);if(!in_array($type,['opening','in','out','closing'],true)||$amount===false||$amount<0)json_response(['ok'=>false,'error'=>'Jenis atau nominal kas tidak valid.'],400);$st=db()->prepare('INSERT INTO cash_movements(movement_type,amount,note) VALUES(?,?,?)');$st->execute([$type,$amount,trim((string)($d['note']??''))?:null]);json_response(['ok'=>true]);
    }
    if ($action === 'profit_report') {
        $from=$_GET['from']??date('Y-m-01');$to=$_GET['to']??date('Y-m-d');
        $st=db()->prepare("SELECT COALESCE(SUM(si.line_total),0) gross_sales, COALESCE(SUM(si.quantity*p.cost_price),0) cogs, COUNT(DISTINCT s.id) transactions FROM sales s JOIN sale_items si ON si.sale_id=s.id LEFT JOIN products p ON p.id=si.product_id WHERE DATE(s.created_at) BETWEEN ? AND ? AND s.status='success'");
        try{$st->execute([$from,$to]);$r=$st->fetch();$st2=db()->prepare('SELECT COALESCE(SUM(amount),0) total_expenses FROM expenses WHERE DATE(created_at) BETWEEN ? AND ?');$st2->execute([$from,$to]);$r['expenses']=$st2->fetchColumn();$r['gross_profit']=(int)$r['gross_sales']-(int)$r['cogs'];$r['net_estimate']=$r['gross_profit']-(int)$r['expenses'];json_response(['ok'=>true,'report'=>$r,'from'=>$from,'to'=>$to]);}catch(PDOException $e){json_response(['ok'=>false,'error'=>'Jalankan upgrade.sql terlebih dahulu untuk fitur laporan laba rugi.'],400);}
    }
    if ($action === 'export_sales') {
        header_remove('Content-Type');header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="kasirku-riwayat.csv"');$out=fopen('php://output','w');fwrite($out,"\xEF\xBB\xBF");fputcsv($out,['Invoice','Tanggal','Subtotal','PPN','Diskon','Total','Metode','Dibayar','Kembalian']);foreach(db()->query('SELECT invoice_no,created_at,subtotal,tax,discount_amount,grand_total,payment_method,paid_amount,change_amount FROM sales ORDER BY id DESC') as $r)fputcsv($out,$r);fclose($out);exit;
    }
    if ($action === 'backup') {
        // Portable SQL backup of application tables; database credentials are not exposed.
        header_remove('Content-Type');header('Content-Type: application/sql; charset=utf-8');header('Content-Disposition: attachment; filename="kasirku-backup-'.date('Ymd-His').'.sql"');$pdo=db();echo "SET FOREIGN_KEY_CHECKS=0;\n";foreach(['products','sales','sale_items','customers','suppliers','purchases','purchase_items','expenses','cash_movements','users'] as $table){$exists=$pdo->query("SHOW TABLES LIKE ".$pdo->quote($table))->fetchColumn();if(!$exists)continue;$create=$pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM);echo "DROP TABLE IF EXISTS `$table`;\n".$create[1].";\n";$rows=$pdo->query("SELECT * FROM `$table`");foreach($rows as $row){$vals=array_map(fn($v)=>$v===null?'NULL':$pdo->quote((string)$v),array_values($row));echo "INSERT INTO `$table` VALUES(".implode(',',$vals).");\n";}}echo "SET FOREIGN_KEY_CHECKS=1;\n";exit;
    }

    json_response(['ok'=>false,'error'=>'Aksi tidak dikenal.'],404);
} catch (Throwable $e) {
    $status = $e instanceof RuntimeException ? 409 : 500;
    error_log((string)$e);
    json_response(['ok'=>false,'error'=>$e->getMessage() ?: 'Terjadi kesalahan server.'], $status);
}
