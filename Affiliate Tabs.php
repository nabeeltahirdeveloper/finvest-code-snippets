add_filter('slicewp_affiliate_account_tabs', 'fa_slicewp_affiliate_account_tabs');
function fa_slicewp_affiliate_account_tabs($tabs){
// 	echo '<pre>';
// 	print_r($tabs);
	
// 	echo '</pre>';
	$tabs['brands_transactions'] = array(
			'label' => __( 'All Transactions', 'slicewp' ),
			'icon'  => slicewp_get_svg( 'outline-chart-pie' )
		);
	
	return $tabs;
}

add_action( 'slicewp_affiliate_account_tab_brands_transactions_top', 'fa_brands_brands_transactions_tab_content' );
function fa_brands_brands_transactions_tab_content(){
	global $wpdb;
	$affiliate_id = get_affiliate_id_by_user();

	if(!$affiliate_id){
		return;
	}
	
	$results = $wpdb->get_results( 
		$wpdb->prepare( 
			"SELECT id FROM {$wpdb->prefix}slicewp_affiliates WHERE parent_id = %d", 
			$affiliate_id 
		), 
		ARRAY_A 
	);

	// Step 2: Extract IDs into a simple array
	$affiliate_ids = wp_list_pluck($results, 'id');

	// Optional: Ensure all values are integers
	$affiliate_ids = array_map('intval', $affiliate_ids);
	$affiliate_ids[]=$affiliate_id;
	
	$affiliate_ids = implode(',', $affiliate_ids);
// 	print_r($affiliate_ids);
	//echo '<div id="table-loader" style="position:absolute;top:0;left:0;width:100%;height:100%;background:rgba(255,255,255,0.8);z-index:9999;display:flex;align-items:center;justify-content:center;font-size:18px;font-weight:bold;">Loading...</div>';
	echo do_shortcode('[brand-commissions affiliate_id="'.$affiliate_ids.'"]');
}

add_action( 'slicewp_affiliate_account_tab_network_top', 'fa_brands_network_tab_content' );
function fa_brands_network_tab_content(){
	echo do_shortcode('[my-network]');
}

add_shortcode('brand-commissions', 'fa_get_brand_commissions');
function fa_get_brand_commissions($atts){
	global $wpdb;
	$attr = shortcode_atts(array(
			'affiliate_id' => 0,
	), $atts);
	$affiliate_ids = explode(',', $attr['affiliate_id']);

	if (!empty($affiliate_ids)) {
		$placeholders = implode(',', array_fill(0, count($affiliate_ids), '%d'));
		$query = "SELECT * FROM {$wpdb->prefix}slicewp_commissions WHERE affiliate_id IN ($placeholders)";

		$results = $wpdb->get_results(
			$wpdb->prepare($query, ...$affiliate_ids),
			ARRAY_A
		);
	} else {
		$results = []; // Or handle the case where no IDs are provided
	}
	
	$table_rows = '';
	$total_commission_amount = 0;
	$approved_paid_commission = 0;
	$unpaid_commission = 0;
	$total_order_amount = 0;
	$rolling_reserve = 0;
	// 	$unpaid = array_values(array_filter($results, function($row){
	// 		return isset($row['status']) && strcasecmp($row['status'], 'unpaid') === 0;
	// 	}));
	// 	echo "<pre>";print_r($unpaid);exit;
	if($affiliate_ids[0]){
		$affiliate_data = fv_get_affiliate_commission_meta( $affiliate_ids[0] );
		$ccommission_rate_sale = $affiliate_data['commission_rate_sale'];
		$commission_rate_type_sale = $affiliate_data['commission_rate_type_sale'];
	}else{
		$ccommission_rate_sale = 0;
		$commission_rate_type_sale = "";
	}
	//echo "<pre>";print_r($affiliate_data);
	//echo "<pre>";print_r($commission_rate_sale);
	//echo "<pre>";print_r($commission_rate_type_sale);exit;
	
	$unpaid_total_usd = 0.0;
	$paid_total_usd = 0.0;
	foreach ( $results as $row ) {
		// do something with $row
		ob_start();
		$id = $row['id'];
		
        $amount = $row['amount'] ?? '';
		
		
		
        $order_id = $row['reference'] ?? '';
        $order_amount = $row['reference_amount'] ?? '';
        $date = $row['date_created'] ?? '';
        $status = $row['status'] ?? '';
		$affiliate_id = $row['affiliate_id'] ?? '';
		
		if($status == 'paid'){
			$approved_paid_commission += (float) $payout_usd;
			$total_commission_amount += (float) $payout_usd;
		}
		if($status == 'unpaid'){
			$unpaid_commission += (float) $payout_usd;
			$total_commission_amount += (float) $payout_usd;
		}
		
		$user_id = $wpdb->get_var( 
			$wpdb->prepare( 
				"SELECT user_id FROM {$wpdb->prefix}slicewp_affiliates WHERE id = %d", 
				$affiliate_id
			) 
		);
        $user = get_userdata( $user_id );
		
		$brand_name = esc_html( $user->user_login );

        $product_details = get_post_meta($order_id, 'product_details', true);
        $billing_details = get_post_meta($order_id, 'billing_details', true);
        $payment_details = get_post_meta($order_id, 'order_details', true);

        $first_name = $billing_details['first_name'] ?? '';
        $last_name  = $billing_details['last_name'] ?? '';
        $email      = $billing_details['email'] ?? '';
        $phone      = $billing_details['phone'] ?? '';
        $country    = strtoupper($billing_details['country'] ?? '');
        $p_currency = $payment_details['currency'] ?? '';
        $p_amount   = $payment_details['amount'] ?? '';
        $message    = $payment_details['result']['description'] ?? '';
        $code       = $payment_details['result']['code'] ?? '';
		$card_holder= $payment_details['card']['holder'] ?? '';
		$order_status = get_post_meta($order_id, 'finvest_order_status', true);
		
		$usd_order_amount = fa_get_slicewp_commission_meta( $id, '_reference_amount_in_usd', true );
		
		if(!$usd_order_amount){
			$usd_order_amount = fa_convert_to_usd_api($order_amount, $p_currency);
		}
		else{
			$usd_order_amount = 'USD '.round($usd_order_amount, 2);
		}
		
		$order_amount_usd_numeric = floatval(preg_replace('/[^\d.]/', '', $usd_order_amount));
		$rolling_reserve_amount = 'USD ' . number_format($order_amount_usd_numeric * 0.10, 2);
		
		$rolling_reserve += (float) number_format($order_amount_usd_numeric * 0.10, 2);
		
		$usd_amount_order = str_replace(' ', '', $usd_order_amount);
		$usd_amount_order = str_replace('USD', '', $usd_order_amount);
		
		// Get brand commission rate from database
		$commission_rate = $wpdb->get_var( 
			$wpdb->prepare( 
				"SELECT meta_value FROM {$wpdb->prefix}slicewp_affiliate_meta WHERE slicewp_affiliate_id = %d AND meta_key = %s", 
				$affiliate_id,
				'commission_rate_sale'
			) 
		);
		
		// Convert commission rate to decimal (e.g., 65 -> 0.65)
		$commission_rate_decimal = $commission_rate ? (float)$commission_rate / 100 : 0;
		
		// Calculate payout USD based on commission rate and USD order amount
		$payout_usd = $order_amount_usd_numeric * $commission_rate_decimal;
		
		// summary variables
		$total_order_amount += (float) $usd_amount_order;
		
		// Get user IP from commission meta
		        $user_ip = fa_get_commission_user_ip($id);
        if (empty($user_ip)) {
            // Fallback to order meta if commission meta doesn't have IP
            $user_ip = get_post_meta($order_id, 'user_ip', true);
        }
        $user_ip = $user_ip ?: 'Unknown';
        
        // Get VPN/Proxy status
        $vpn_status = fa_get_commission_vpn_status($id);
        if (empty($vpn_status)) {
            // Fallback to order meta if commission meta doesn't have VPN status
            $vpn_status = get_post_meta($order_id, 'vpn_proxy_status', true);
        }
        $vpn_status = $vpn_status ?: 'Unknown';
        
        // Get VPN/Proxy geographic location (country)
        $vpn_geo = 'Unknown';
        $vpn_data = fa_get_commission_vpn_data($id);
        if (empty($vpn_data)) {
            // Fallback to order meta if commission meta doesn't have VPN data
            $vpn_data = get_post_meta($order_id, 'vpn_proxy_data', true);
        }
        
        // Debug log to check what data we're retrieving
        error_log("Commission ID {$id}: VPN Status = {$vpn_status}, VPN Data = " . json_encode($vpn_data));
        
        if (!empty($vpn_data) && isset($vpn_data['location']['country'])) {
            $vpn_geo = $vpn_data['location']['country'];
            // Also include country code for clarity
            if (isset($vpn_data['location']['country_code'])) {
                $vpn_geo .= ' (' . $vpn_data['location']['country_code'] . ')';
            }
        } elseif ($vpn_status === 'Not Detected (Local IP)') {
            $vpn_geo = 'Local Network';
        } elseif ($vpn_status === 'Detection Failed') {
            $vpn_geo = 'Detection Failed';
        }
        
        // Get card BIN and bank information
        $card_bin = fa_get_commission_card_bin($id);
        $card_bin_info = fa_get_commission_card_bin_info($id);
        
        if (empty($card_bin)) {
            // Fallback to order meta if commission meta doesn't have BIN
            $card_bin = get_post_meta($order_id, 'card_bin', true);
        }
        
        if (empty($card_bin_info)) {
            // Fallback to order meta if commission meta doesn't have BIN info
            $bin_info_json = get_post_meta($order_id, 'card_bin_info', true);
            $card_bin_info = $bin_info_json ? json_decode($bin_info_json, true) : null;
        }
        
        $card_bin = $card_bin ?: 'Unknown';
        $bank_name = 'Unknown';
        
        if (!empty($card_bin_info) && isset($card_bin_info['bank'])) {
            $bank_name = $card_bin_info['bank'];
            // Truncate long bank names for display
            if (strlen($bank_name) > 25) {
                $bank_name = substr($bank_name, 0, 25) . '...';
            }
        }

        if (strpos($code, '000.000') === 0 || strpos($code, '000.100.1') === 0) {
            $message = '<span class="success-message">' . esc_html($message) . '</span>';
        } else {
            $message = '<span class="error-message">' . esc_html($message) . '</span>';
        }
		if (strcasecmp($status, 'unpaid') === 0) {
			$unpaid_total_usd += $order_amount_usd_numeric; // sum numeric USD
		}
		if (strcasecmp($status, 'paid') === 0) {
			$paid_total_usd += $order_amount_usd_numeric; // sum numeric USD for PAID orders
		}
		
		$action = '<form method="post">
						<input type="hidden" name="security" value="'. wp_create_nonce('fa_ajax_nonce') . '">
						<input type="hidden" name="commission_id" value="'.$id.'">
						<input type="hidden" name="action" value="fa_update_transaction_status">
						<input type="hidden" name="affiliate_id" value="'.$affiliate_id.'">
						<select name="status">
							<option value="">Select Status</option>
							<option value="paid">Paid</option>
							<option value="unpaid">Unpaid</option>
							<option value="pending">Pending</option>
							<option value="rejected">Rejected</option>
						</select>
						<button type="submit">Update</button>
					</form>';

        echo '<tr>';
        echo '<td class="all">' . esc_html($id) . '</td>';
		echo '<td class="all">' . esc_html($brand_name) . '</td>';
		echo '<td class="all">' . esc_html($first_name) . '</td>';
        echo '<td class="all">' . esc_html($last_name) . '</td>';
        echo '<td class="all">' . esc_html($email) . '</td>';
		echo '<td class="all">' . esc_html($country) . '</td>';
		// Use p_amount for display if order_amount seems incorrect (like showing 1 instead of actual amount)
		$display_amount = ($order_amount > 0 && $order_amount < 10 && $p_amount > 10) ? $p_amount : $order_amount;
		echo '<td class="all">' . $p_currency . ' ' . esc_html(round($display_amount, 2)) . '</td>';
		echo '<td class="all">' . esc_html($date) . '</td>';
		echo '<td>' . esc_html($card_holder) . '</td>';
        echo '<td>' . esc_html($phone) . '</td>';
        echo '<td>#' . esc_html($order_id) . '</td>';
		        echo '<td>' . esc_html($usd_order_amount) . '</td>';
		echo '<td>USD ' . esc_html(round($payout_usd, 2)) . '</td>';
		echo '<td>' . esc_html($rolling_reserve_amount) . '</td>';
        echo '<td class="all"> <span class="transaction-status '.$status.'">' . esc_html($status) . '</span></td>';
        echo '<td>' . esc_html($user_ip) . '</td>';
        echo '<td><span class="vpn-status ' . strtolower(str_replace(' ', '-', $vpn_status)) . '">' . esc_html($vpn_status) . '</span></td>';
        echo '<td><span class="vpn-geo">' . esc_html($vpn_geo) . '</span></td>';
        echo '<td><span class="card-bin">' . esc_html($card_bin) . '</span><br><small class="bank-name">' . esc_html($bank_name) . '</small></td>';
        echo '<td>' . $message . '</td>';
		echo '<td>' . $action . '</td>';
        echo '</tr>';
		$t_row = ob_get_contents();
		ob_end_clean();
		
   		$table_rows .= $t_row; 
	}
	$unpaid_total_formatted = '' . number_format($unpaid_total_usd, 2);
	$unpaid_ten_percent_usd       = $unpaid_total_usd * 0.10;
	$unpaid_ten_percent_formatted = number_format($unpaid_ten_percent_usd, 2);
	// If you want the label:
	$unpaid_ten_percent_labeled   = '' . $unpaid_ten_percent_formatted;
	$paid_total_formatted = '' . number_format($paid_total_usd, 2);
	ob_start();
	
	echo '<input type="hidden" name="commission_id_rate" id="commission_id_rate" value="'.$ccommission_rate_sale.'"><span class="sum_card"><span class="icn_sum"><svg xmlns="http://www.w3.org/2000/svg" width="512" height="512" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"><path d="M3.5 4v13.5a3 3 0 0 0 3 3H20"/><path d="m6.5 15l4.5-4.5l3.5 3.5L20 8.5"/></g></svg></span>Summary Report</span>';
	echo '<div class="summary_wrap">';
	echo '<div class="card total-order-amount">
		<span class="icn"><svg xmlns="http://www.w3.org/2000/svg" width="512" height="512" viewBox="0 0 20 20"><path fill="currentColor" d="M6 13h9c.55 0 1 .45 1 1s-.45 1-1 1H5c-.55 0-1-.45-1-1V4H2c-.55 0-1-.45-1-1s.45-1 1-1h3c.55 0 1 .45 1 1v2h13l-4 7H6v1zm-.5 3c.83 0 1.5.67 1.5 1.5S6.33 19 5.5 19S4 18.33 4 17.5S4.67 16 5.5 16zm9 0c.83 0 1.5.67 1.5 1.5s-.67 1.5-1.5 1.5s-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5z"/></svg></span>
      <h4>Total Order Amount</h4>
      <p>$</p>
    </div>
    <div class="card rolling_reserve">
		<span class="icn"><svg xmlns="http://www.w3.org/2000/svg" width="512" height="512" viewBox="0 0 512 512"><path fill="currentColor" d="m466.5 83.7l-192-80a48.15 48.15 0 0 0-36.9 0l-192 80C27.7 91.1 16 108.6 16 128c0 198.5 114.5 335.7 221.5 380.3c11.8 4.9 25.1 4.9 36.9 0C360.1 472.6 496 349.3 496 128c0-19.4-11.7-36.9-29.5-44.3zM256.1 446.3l-.1-381l175.9 73.3c-3.3 151.4-82.1 261.1-175.8 307.7z"/></svg></span>
      <h4>Rolling Reserve 10% (120 days)</h4>
      <p>$</p>
    </div>
    <div class="card final_payout">
		<span class="icn"><svg xmlns="http://www.w3.org/2000/svg" width="512" height="512" viewBox="0 0 24 24"><path fill="currentColor" fill-rule="evenodd" d="M12 21a9 9 0 1 0 0-18a9 9 0 0 0 0 18m-.232-5.36l5-6l-1.536-1.28l-4.3 5.159l-2.225-2.226l-1.414 1.414l3 3l.774.774z" clip-rule="evenodd"/></svg></span>
      <h4>Final Payout Amount</h4>
      <p>$</p>
    </div>
	
    <div class="card approved-unpaid_payout">
		<span class="icn"><svg xmlns="http://www.w3.org/2000/svg" width="512" height="512" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10s10-4.5 10-10S17.5 2 12 2zm3.5 12c-.3.5-.9.6-1.4.4l-2.6-1.5c-.3-.2-.5-.5-.5-.9V7c0-.6.4-1 1-1s1 .4 1 1v4.4l2.1 1.2c.5.3.6.9.4 1.4z"/></svg></span>
      <h4>Total paid</h4>
      <p>$</p>
    </div>
    ';
// 	echo $total_commission_amount.'<br>';
// 	echo $approved_paid_commission.'<br>';
// 	echo $unpaid_commission.'<br>';
// 	echo $total_order_amount .'<br>';
// 	echo $rolling_reserve .'<br>';
	
	echo '</div>';
	
	echo '<style>
	.vpn-status.detected { color: #d63638; font-weight: bold; }
.vpn-status.not-detected { color: #00a32a; font-weight: bold; }
.vpn-status.unknown, .vpn-status.detection-failed { color: #826135; }
.vpn-status.not-detected-local-ip { color: #72777c; font-style: italic; }
.vpn-geo { color: #2271b1; font-weight: 500; }
.card-bin { color: #50575e; font-weight: 600; font-family: monospace; }
.bank-name { color: #2271b1; font-weight: 400; font-style: italic; font-size: 11px; }

	</style>';
	//echo '<div id="table-loader" style="position:absolute;top:0;left:0;width:100%;height:100%;background:rgba(255,255,255,0.8);z-index:9999;display:flex;align-items:center;justify-content:center;font-size:18px;font-weight:bold;">Loading...</div>';

	echo '<span class="sum_filter"><span class="icn_fun"><svg xmlns="http://www.w3.org/2000/svg" width="512" height="512" viewBox="0 0 512 512"><path fill="currentColor" d="M296 464a23.88 23.88 0 0 1-7.55-1.23L208.3 436.1a23.92 23.92 0 0 1-16.3-22.78V294.11a.44.44 0 0 0-.09-.13L23.26 97.54A30 30 0 0 1 46.05 48H466a30 30 0 0 1 22.79 49.54L320.09 294a.77.77 0 0 0-.09.13V440a23.93 23.93 0 0 1-24 24Z"/></svg></span>Professional Filter System</span>';
	echo '<div class="fa-table-wrapp"><div class="filters_all"><div id="filters" style="margin-bottom: 10px;">
	<label class="dt_range"><span class="dt_icn"><svg xmlns="http://www.w3.org/2000/svg" width="512" height="512" viewBox="0 0 20 20"><path fill="currentColor" d="M1 4c0-1.1.9-2 2-2h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V4zm2 2v12h14V6H3zm2-6h2v2H5V0zm8 0h2v2h-2V0zM5 9h2v2H5V9zm0 4h2v2H5v-2zm4-4h2v2H9V9zm0 4h2v2H9v-2zm4-4h2v2h-2V9zm0 4h2v2h-2v-2z"/></svg></span>Date Range Selection</label>
  <label>From Date: <input type="date" id="min-date"></label>
  <label>To Date: <input type="date" id="max-date"></label>
</div><div id="filters_control" style="margin-bottom: 10px;"><label class="fil_con"><span class="control_icn"><svg xmlns="http://www.w3.org/2000/svg" width="512" height="512" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round"><path d="M5 12V4m14 16v-3M5 20v-4m14-3V4m-7 3V4m0 16v-9"/><circle cx="5" cy="14" r="2"/><circle cx="12" cy="9" r="2"/><circle cx="19" cy="15" r="2"/></g></svg></span>Filter Control</label></div></div><div class="main_tbl"><table style="display:none;" class="table dt-responsive transactions-table" border="1" cellpadding="8" cellspacing="0">';
    
    // Table Header
    echo '<thead><tr>';
    echo '<th class="all">ID</th>';
	echo '<th class="all">Brand Name</th>';
	echo '<th class="all">First Name</th>';
    echo '<th class="all">Last Name</th>';
    echo '<th class="all">Email</th>';
	echo '<th class="all">GEO</th>';
	echo '<th class="all">Order Amount</th>';
	echo '<th class="all">Date</th>';
	echo '<th>Card Holder Name</th>';
    echo '<th>Phone</th>';
    echo '<th>Order ID</th>';
	echo '<th>Order Amount USD</th>';
	echo '<th>Payout USD</th>';
	echo '<th>Rolling Reserve</th>';
    echo '<th class="all">Status</th>';
    echo '<th>User IP</th>';
    echo '<th>VPN/PROXY</th>';
    echo '<th>VPN PROXY GEO</th>';
    echo '<th>CARD BIN</th>';
//     echo '<th>Payment Currency</th>';
//     echo '<th>Payment Amount</th>';
    echo '<th>Payment Message</th>';
	echo '<th>Action</th>';
    echo '</tr></thead>';
	// Table Body
    echo '<tbody>';
	
	echo $table_rows;
	
		
 	echo '</tbody>';
    echo '</table></div>';
	$unpaid_total_formatted = 'USD ' . number_format($unpaid_total_usd, 2);
	//echo '<p>Total unpaid amount: ' . $unpaid_total_formatted . '</p>';
		
	$output = ob_get_contents();
	ob_end_clean();
	
	return $output;
}

function fa_get_slicewp_commission_meta( $commission_id, $meta_key, $single = true ) {
    global $wpdb;

    $table = $wpdb->prefix . 'slicewp_commission_meta';

    $results = $wpdb->get_col( $wpdb->prepare(
        "SELECT meta_value FROM {$table} WHERE slicewp_commission_id = %d AND meta_key = %s",
        $commission_id,
        $meta_key
    ) );

    if ( empty( $results ) ) {
        return $single ? '' : [];
    }

    $results = array_map( 'maybe_unserialize', $results );

    return $single ? $results[0] : $results;
}

// Helper function to get user IP from commission
function fa_get_commission_user_ip( $commission_id ) {
    return fa_get_slicewp_commission_meta( $commission_id, '_user_ip', true );
}

// Helper function to get VPN/Proxy status from commission
function fa_get_commission_vpn_status( $commission_id ) {
    return fa_get_slicewp_commission_meta( $commission_id, '_vpn_proxy_status', true );
}

// Helper function to get detailed VPN/Proxy data from commission
function fa_get_commission_vpn_data( $commission_id ) {
    $data = fa_get_slicewp_commission_meta( $commission_id, '_vpn_proxy_data', true );
    return $data ? json_decode($data, true) : null;
}

// Helper function to get card BIN from commission
function fa_get_commission_card_bin( $commission_id ) {
    return fa_get_slicewp_commission_meta( $commission_id, '_card_bin', true );
}

// Helper function to get detailed card BIN information from commission
function fa_get_commission_card_bin_info( $commission_id ) {
    $data = fa_get_slicewp_commission_meta( $commission_id, '_card_bin_info', true );
    return $data ? json_decode($data, true) : null;
}

add_shortcode('my-network', 'fa_get_affilate_subbrands');
function fa_get_affilate_subbrands(){
	global $wpdb;
	
	ob_start();
	// update commission status
	if(!empty($_POST['affiliate_id']) && !empty($_POST['commission_id']) && !empty($_POST['status'])){
		
		check_ajax_referer('fa_ajax_nonce', 'security');
		global $wpdb;
		$parent_affiliate_id = get_affiliate_id_by_user();
		$affiliate_id = $_POST['affiliate_id'];
		$commission_id = $_POST['commission_id'];
		$status = $_POST['status'];
		
		$parent_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT parent_id FROM {$wpdb->prefix}slicewp_affiliates WHERE id = %d", 
				$affiliate_id 
			) 
		);
		
		if($parent_id != $parent_affiliate_id){
			return;
		}
		
		$wpdb->update(
			"{$wpdb->prefix}slicewp_commissions", // table name
			array( 'status' => $status ),        // data to update
			array( 'id' => $commission_id ),                  // where condition
			array( '%s' ),                        // format of data
			array( '%d' )                         // format of where
		);
		
		if ( $wpdb->rows_affected ) {
			echo '<p class="success-message">Commission updated successfully.</p>';
		} else {
			echo '<p class="error-message">No changes made or invalid ID.</p>';
		}

	}
	
	
	// display transactions of member

	if(!empty($_GET['affilate_id'])){
		ob_start();
		echo '<a href="/brand-dashboard/?affiliate-account-tab=network" class="back-to-network-button">Back to Network</a>';
		
		$user_id = $wpdb->get_var( 
			$wpdb->prepare( 
				"SELECT user_id FROM {$wpdb->prefix}slicewp_affiliates WHERE id = %d", 
				$_GET['affilate_id'] 
			) 
		);
        $user = get_userdata( $user_id );
		
		echo '<h2 class="transactions-heading">'.esc_html( $user->user_login ).' Transactions</h2>';
		
		echo do_shortcode('[brand-commissions affiliate_id="'.$_GET['affilate_id'].'"]');
		$output = ob_get_contents();
		ob_end_clean();
		return $output;
	}
	
	
	
	// display Network members in table
	
	$affiliate_id = get_affiliate_id_by_user();

	if(!$affiliate_id){
		return;
	}
	$results = $wpdb->get_results( 
		$wpdb->prepare( 
			"SELECT * FROM {$wpdb->prefix}slicewp_affiliates WHERE parent_id = %d", 
			$affiliate_id 
		), 
		ARRAY_A 
	);
	

	
	echo '<div class="fa-table-wrapp"><table class="brands-table" border="1" cellpadding="8" cellspacing="0">';
    
    // Table Header
    echo '<thead><tr>';
//     echo '<th>Affiliate ID</th>';
    echo '<th>Username</th>';
    echo '<th>Email</th>';
//     echo '<th>First Name</th>';
//     echo '<th>Last Name</th>';
	echo '<th>Transactions</th>';
	echo '<th>Payouts</th>';
	echo '<th class="action">Action</th>';
    echo '</tr></thead>';

    
    // Table Body
    echo '<tbody>';
	foreach ( $results as $row ) {
		// do something with $row
		if ( ! empty( $results ) ) {
    
        $user_id = $row['user_id'];
        $user = get_userdata( $user_id );
			
			
		$transactions = fa_affiliate_transactions($row['id']);
		if(!empty($transactions)){
			$payout = 0;
			$data = [];
			foreach($transactions as $t){
				$data[$t['status']][] = $t;
				if($t['status'] == 'unpaid' || $t['status'] == 'paid'){
					$payout += fa_calculate_payout_usd($t, $row['id']);
				}
			}
			
			if(!empty($data['unpaid'])){
				$unpaid = count($data['unpaid']);
			} else{ $unpaid = 0; }
			
			if(!empty($data['paid'])){
				$paid = count($data['paid']);
			} else{ $paid = 0; }
			
			$transactions_count = (int)$unpaid + (int)$paid;
		} else{
			$transactions_count = 0;
			$payout = 0;
		}

        echo '<tr>';
//         echo '<td>' . esc_html( $row['id'] ) . '</td>';

        if ( $user ) {
            echo '<td>' . esc_html( $user->user_login ) . '</td>';
            echo '<td>' . esc_html( $user->user_email ) . '</td>';
//             echo '<td>' . esc_html( get_user_meta( $user_id, 'first_name', true ) ) . '</td>';
//             echo '<td>' . esc_html( get_user_meta( $user_id, 'last_name', true ) ) . '</td>';
			echo '<td>' . esc_html( $transactions_count ) . '</td>';
			echo '<td>' . esc_html( '$'.$payout ) . '</td>';
			echo '<td class="action"><a href="/brand-dashboard/?affiliate-account-tab=network&affilate_id='.$row['id'].'" class="view-transation-button">View Transactions</a></td>';
        } else {
            echo '<td colspan="4">User not found.</td>';
        }
        echo '</tr>';
		}
	}
    echo '</tbody>';
    echo '</table></div>';

	$output = ob_get_contents();
	ob_end_clean();
	
	return $output;
}


// get loged in user affiliate id
function get_affiliate_id_by_user() {
    if ( ! is_user_logged_in() ) {
        return false;
    }

    $user_id = get_current_user_id();

    global $wpdb;
    return $wpdb->get_var( 
        $wpdb->prepare( 
            "SELECT id FROM {$wpdb->prefix}slicewp_affiliates WHERE user_id = %d", 
            $user_id 
        ) 
    );
}


add_action('wp_footer', 'load_datatables_on_specific_page');
function load_datatables_on_specific_page() {
    if ( is_page(1213) ) {
        ?>
        <!-- DataTables CSS -->
        <link rel="stylesheet" href="/wp-content/uploads/datatable/jquery.dataTables.min.css">
        <link rel="stylesheet" href="/wp-content/uploads/datatable/buttons.dataTables.min.css">
		<link rel="stylesheet" href="/wp-content/uploads/datatable/dataTables.responsive.css">
        <!-- jQuery (if not already loaded) -->
        <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
        <!-- DataTables JS -->
        <script src="/wp-content/uploads/datatable/jquery.dataTables.min.js"></script>
        <script src="/wp-content/uploads/datatable/dataTables.buttons.min.js"></script>
        <script src="/wp-content/uploads/datatable/buttons.html5.min.js"></script>
        <script src="/wp-content/uploads/datatable/jszip.min.js"></script>
		<script src="/wp-content/uploads/datatable/dataTables.responsive.js"></script>

        <script>
			jQuery(document).ready(function($) {
			  $('.slicewp-nav-tab[data-slicewp-tab]').on('click', function(e) {
				e.preventDefault(); // Prevent default anchor behavior

				// Get the data attribute (if you want to pass it as hash or query)
				var tab = $(this).data('slicewp-tab');

				if (tab) {
					$('#slicewp-affiliate-account-tab .slicewp-tab').css({
						'opacity': '0.5',
						'filter': 'blur(5px)'
					});
				  // Reload with tab info in hash or query string
				  window.location.href = window.location.pathname + '?affiliate-account-tab=' + tab;
				} else {
				  // If no tab, just reload the page
				  location.reload();
				}
			  });
			});
 
			
			
        jQuery(document).ready(function($) {
		
// 	make_datatable();	
// 	function make_datatable(){
		if($('table').hasClass('brands-table')){
					
            $('.fa-table-wrapp table.brands-table').DataTable({
                dom: 'Blfrtip',
                buttons: ['csvHtml5'],
				order: [[0, 'desc']],
				responsive: true,
				pageLength: 40,
				lengthMenu: [ [20, 40, 50, 100, -1], [20, 40, 50, 100, "All"] ],
				columnDefs: [
					{
						targets: 0, // Second-last column
						responsivePriority: 2,
						className: 'all'
					},
					{
						targets: 1, // Second-last column
						responsivePriority: 2,
						className: 'all'
					},
					{
						targets: 2, // Last column
						responsivePriority: 1,
						className: 'all'
					}
					,
					{
						targets: 3, // Last column
						responsivePriority: 1,
						className: 'all'
					}
					,
					{
						targets: 4, // Last column
						responsivePriority: 1,
						className: 'all'
					}
// 					{
// 						targets: [0, 1, 2, 3, 4, 5, 6, 7, 8], // All others (example)
// 						responsivePriority: 100,
// 						className: 'none'
// 					}
				]
            });
			
			// Custom date range filter for column index 12
$.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
    let min = $('#min-date').val();
    let max = $('#max-date').val();
    let date = data[7]; // column 12

    if (!date) return true;

    // Convert "2025-07-11 17:11:10" → "2025-07-11"
    let dateOnly = date.split(' ')[0];
    let rowDate = new Date(dateOnly);
    let minDate = min ? new Date(min) : null;
    let maxDate = max ? new Date(max) : null;

    if ((minDate === null || rowDate >= minDate) &&
        (maxDate === null || rowDate <= maxDate)) {
        return true;
    }
    return false;
});
		}
			



$(document).ready(function () {
	if($('table').hasClass('transactions-table')){
	
    const table = $('.fa-table-wrapp table.transactions-table').DataTable({
        dom: '<"top"Blfip>rt<"bottom"ip>',  
        buttons: [
				{
					extend: 'csvHtml5',
					text: '<svg class="btn-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M21 14a1 1 0 0 0-1 1v4a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-4a1 1 0 0 0-2 0v4a3 3 0 0 0 3 3h14a3 3 0 0 0 3-3v-4a1 1 0 0 0-1-1Zm-9.71 1.71a1 1 0 0 0 .33.21a.94.94 0 0 0 .76 0a1 1 0 0 0 .33-.21l4-4a1 1 0 0 0-1.42-1.42L13 12.59V3a1 1 0 0 0-2 0v9.59l-2.29-2.3a1 1 0 1 0-1.42 1.42Z"/></svg><span class="btn-label">Export CSV</span>',
					className: 'btn btn-primary exp'
				},
				{
					text: 'Reset Filters',
					className: 'btn btn-secondary',
					action: function (e, dt) {
						const $container = $('#filters_control');
						$container.find('select').val('');
						$('#min-date, #max-date').val('');
						$(dt.table().container()).find('input[type="search"]').val('');
						dt.search('');
						dt.columns().search('');
						dt.draw();
						if (typeof updateFilteredTotal === 'function') updateFilteredTotal(dt);
					}
				},
				{
				  text: 'Refresh Table',
				  className: 'btn btn-outline-secondary',
				  action: function () {
					location.reload(); // reloads the page
				  }
				}
			],
        order: [[0, 'desc']],
        responsive: true,
        pageLength: 20,
        lengthMenu: [[20, 40, 50, 100, -1], [20, 40, 50, 100, "All"]],
        columnDefs: [
            { targets: 0, responsivePriority: 2, className: 'all' },
            { targets: 1, responsivePriority: 2, className: 'all', orderable: false },
            { targets: 2, responsivePriority: 1, className: 'all' },
            { targets: 3, responsivePriority: 1, className: 'all' },
            { targets: 4, responsivePriority: 1, className: 'all' },
			{ targets: 14, orderable: false },
			{ targets: 5, orderable: false },
			{ targets: 8, orderable: false }
        ],
        initComplete: function () {
			const filterContainer = $('#filters_control');
			$(this).css('display', 'block');
			$('#table-loader').fadeOut();
            // Dropdown filter for "Status" column (index 14)
            this.api().columns(14).every(function () {
                const column = this;
				const wrapper = $('<div class="filter-item"><label>Status: </label></div>').appendTo(filterContainer);
                const select = $('<select><option value="">All Status</option></select>')
                    .appendTo(wrapper)
                    .on('change', function () {
                        const val = $.fn.dataTable.util.escapeRegex($(this).val());
                        column.search(val ? '^' + val + '$' : '', true, false).draw();
                    });

                column.data().unique().sort().each(function (d) {
                    if (d) {
                        const text = $('<div>').html(d).text();
                        select.append('<option value="' + text + '">' + text + '</option>');
                    }
                });
            });
			
			this.api().columns(1).every(function () {
                const column = this;
				const wrapper = $('<div class="filter-item"><label>Brand: </label></div>').appendTo(filterContainer);
                const select = $('<select><option value="">All Brands</option></select>')
                    .appendTo(wrapper)
                    .on('change', function () {
                        const val = $.fn.dataTable.util.escapeRegex($(this).val());
                        column.search(val ? '^' + val + '$' : '', true, false).draw();
                    });

                column.data().unique().sort().each(function (d) {
                    if (d) {
                        const text = $('<div>').html(d).text();
                        select.append('<option value="' + text + '">' + text + '</option>');
                    }
                });
            });
			
			this.api().columns(5).every(function () {
                const column = this;
				const wrapper = $('<div class="filter-item"><label>Geo/Location: </label></div>').appendTo(filterContainer);
                const select = $('<select><option value="">GEO</option></select>')
                    .appendTo(wrapper)
                    .on('change', function () {
                        const val = $.fn.dataTable.util.escapeRegex($(this).val());
                        column.search(val ? '^' + val + '$' : '', true, false).draw();
                    });

                column.data().unique().sort().each(function (d) {
                    if (d) {
                        const text = $('<div>').html(d).text();
                        select.append('<option value="' + text + '">' + text + '</option>');
                    }
                });
            });
        }
    });

    // Redraw table on min/max date change
    $('#min-date, #max-date').on('change', function () {
        table.draw();
		updateFilteredTotal(table);
    });
		
	table.on('draw', function () {
		updateFilteredTotal(table);
	});

	// Initial call
	updateFilteredTotal(table);
		
	}
	
	function updateFilteredTotal(table) {
		const total_order_amount_usd = table
			.column(10, { search: 'applied' })
			.data()
			.reduce(function (sum, val) {
				const num = parseFloat($('<div>').html(val).text().replace(/[^0-9.-]+/g, ''));
				return sum + (isNaN(num) ? 0 : num);
			}, 0);

		//$('.summary_wrap .card.total-order-amount p').text('$' + total_order_amount_usd.toFixed(2));
		
		const total_approved_unpaid_payout = table
			.column(11, { search: 'applied' })
			.data()
			.reduce(function (sum, val) {
				const num = parseFloat($('<div>').html(val).text().replace(/[^0-9.-]+/g, ''));
				return sum + (isNaN(num) ? 0 : num);
			}, 0);

		//$('.summary_wrap .card.approved-unpaid_payout p').text('$' + total_approved_unpaid_payout.toFixed(2));
		
		const total_rolling_reserve = table
			.column(12, { search: 'applied' })
			.data()
			.reduce(function (sum, val) {
				const num = parseFloat($('<div>').html(val).text().replace(/[^0-9.-]+/g, ''));
				return sum + (isNaN(num) ? 0 : num);
			}, 0);

		//$('.summary_wrap .card.rolling_reserve p').text('$' + total_rolling_reserve.toFixed(2));
		
		const final_payout = total_approved_unpaid_payout - total_rolling_reserve;
		//$('.summary_wrap .card.final_payout p').text('$' + final_payout.toFixed(2));
		
		
		/*** NEW: calculate total of UNPAID orders only ***/
		let unpaid_total = 0;
		let paid_total = 0;
		
		table.rows({ search: 'applied' }).every(function () {
			const row = this.data();
			
			// Get the raw text inside the span (status column 14)
			const statusText = $('<div>').html(row[14]).text().trim().toLowerCase();

			if (statusText === 'unpaid') {
				//console.log(row[11]);
				// Column 11 already looks like a number string
				const num = parseFloat(row[11]);
				unpaid_total += isNaN(num) ? 0 : num;
			}
			if (statusText === 'paid') {
				//console.log(row[12]);
				// Column 11 already looks like a number string
				const clean = $('<div>').html(row[12]).text().replace(/[^0-9.\-]+/g, '');
    			const num   = parseFloat(clean);
				paid_total += isNaN(num) ? 0 : num;
			}
		});

		console.log("Final unpaid total (numeric):", unpaid_total);
		$('.summary_wrap .card.total-order-amount p').text('$' + unpaid_total.toFixed(2));
		
		const unpaid_ten_percent = unpaid_total * 0.10;
		console.log(unpaid_ten_percent);
		$('.summary_wrap .card.rolling_reserve p').text('$' + unpaid_ten_percent.toFixed(2));
		
		var commission_id_rate = $('#commission_id_rate').val();
		var commission_percent = parseFloat(commission_id_rate) || 0;
		var commission_value = unpaid_total * (commission_percent / 100);
		console.log(commission_value);
		$('.summary_wrap .card.final_payout p').text('$' + commission_value.toFixed(2));
		
		console.log(paid_total);
		$('.summary_wrap .card.approved-unpaid_payout p').text('$' + paid_total.toFixed(2));
	}

});


// 			}
			
			
        });
        </script>
        <?php
    }
}

add_action('init', function(){
	if(!empty($_GET['testing']) && $_GET['testing'] == 'true'){
		
		foreach ($_COOKIE as $key => $value) {
			echo $key . ' = ' . $value . '<br>';
		}
		
		exit;
		
		$transactions = fa_affiliate_transactions(36);
		$data = [];
		if(!empty($transactions)){
			$data['total_transactions'] = count($transactions);
			
			$payout = 0;
			foreach($transactions as $t){
				
				$data[$t['status']][] = $t;
				
				if($t['status'] == 'unpaid' || $t['status'] == 'paid'){
					$payout += fa_calculate_payout_usd($t, 36);
				}
			}
			
			if(!empty($data['unpaid'])){
				$unpaid = count($data['unpaid']);
			} else{ $unpaid = 0; }
			
			if(!empty($data['paid'])){
				$paid = count($data['paid']);
			} else{ $paid = 0; }
			
			$transactions_count = $unpaid+$paid;
			
			
		}
		
		
		echo '<pre>';
		echo $transactions_count .'='.$payout;
		echo '</pre>';
	}
});

// all affiliate transactions
function fa_affiliate_transactions($affiliate_id){
	global $wpdb;

	$results = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}slicewp_commissions WHERE affiliate_id = %d", 
			$affiliate_id 
		), 
		ARRAY_A 
	);
	
	return $results;
}

// Calculate payout USD for a transaction based on brand commission rate
function fa_calculate_payout_usd($transaction, $affiliate_id) {
	global $wpdb;
	
	// Get brand commission rate from database
	$commission_rate = $wpdb->get_var( 
		$wpdb->prepare( 
			"SELECT meta_value FROM {$wpdb->prefix}slicewp_affiliate_meta WHERE slicewp_affiliate_id = %d AND meta_key = %s", 
			$affiliate_id,
			'commission_rate_sale'
		) 
	);
	
	// Convert commission rate to decimal (e.g., 65 -> 0.65)
	$commission_rate_decimal = $commission_rate ? (float)$commission_rate / 100 : 0;
	
	// Get USD order amount
	$usd_order_amount = fa_get_slicewp_commission_meta( $transaction['id'], '_reference_amount_in_usd', true );
	
	if(!$usd_order_amount){
		// If no USD amount stored, convert from original currency
		$order_amount = $transaction['reference_amount'] ?? 0;
		$usd_order_amount = fa_convert_to_usd_api($order_amount, 'EUR'); // Default to EUR if currency not available
	}
	
	// Calculate payout USD based on commission rate and USD order amount
	$payout_usd = $usd_order_amount * $commission_rate_decimal;
	
	return $payout_usd;
}

// Function to convert currency to USD using exchange rate API
function fa_convert_to_usd_api($amount, $from_currency) {
	// If already USD, return as is
	if (strtoupper($from_currency) === 'USD') {
		return (float)$amount;
	}
	
	// For now, use a simple conversion (you can replace this with a real API call)
	// Common exchange rates (you should use a real API in production)
	$exchange_rates = [
		'EUR' => 1.13, // 1 EUR = 1.13 USD (approximate)
		'GBP' => 1.27, // 1 GBP = 1.27 USD (approximate)
		'CAD' => 0.79, // 1 CAD = 0.79 USD (approximate)
		'AUD' => 0.73, // 1 AUD = 0.73 USD (approximate)
		'JPY' => 0.009, // 1 JPY = 0.009 USD (approximate)
	];
	
	$rate = $exchange_rates[strtoupper($from_currency)] ?? 1.0;
	
	return (float)$amount * $rate;
}