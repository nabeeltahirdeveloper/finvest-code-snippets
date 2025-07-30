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
			$approved_paid_commission += (float) $amount;
			$total_commission_amount += (float) $amount;
		}
		if($status == 'unpaid'){
			$unpaid_commission += (float) $amount;
			$total_commission_amount += (float) $amount;
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
		$order_status = get_post_meta($order_id, 'finvest_order_status', true);
		
		$usd_order_amount = fa_get_slicewp_commission_meta( $id, '_reference_amount_in_usd', true );
		
		if (strtolower($order_status) === 'approved') {
			$order_amount_usd_numeric = floatval(preg_replace('/[^\d.]/', '', $usd_order_amount));
			$rolling_reserve_amount = 'USD ' . number_format($order_amount_usd_numeric * 0.10, 2);
			
			$rolling_reserve += (float) number_format($order_amount_usd_numeric * 0.10, 2);
		} else {
			$rolling_reserve_amount = 'USD 0.00';
		}
		
		if(!$usd_order_amount){
			$usd_order_amount = convert_to_usd_api($order_amount, $p_currency);
		}
		else{
			$usd_order_amount = 'USD '.round($usd_order_amount, 2);
		}
		
		$usd_amount_order = str_replace(' ', '', $usd_order_amount);
		$usd_amount_order = str_replace('USD', '', $usd_order_amount);
		
		
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

        if (strpos($code, '000.000') === 0 || strpos($code, '000.100.1') === 0) {
            $message = '<span class="success-message">' . esc_html($message) . '</span>';
        } else {
            $message = '<span class="error-message">' . esc_html($message) . '</span>';
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
		echo '<td class="all">' . $p_currency . ' ' . esc_html(round($order_amount, 2)) . '</td>';
		echo '<td class="all">' . esc_html($date) . '</td>';
        echo '<td>' . esc_html($phone) . '</td>';
        echo '<td>#' . esc_html($order_id) . '</td>';
		        echo '<td>' . esc_html($usd_order_amount) . '</td>';
		echo '<td>USD ' . esc_html(round($amount, 2)) . '</td>';
		echo '<td>' . esc_html($rolling_reserve_amount) . '</td>';
        echo '<td class="all"> <span class="transaction-status '.$status.'">' . esc_html($status) . '</span></td>';
        echo '<td>' . esc_html($user_ip) . '</td>';
        echo '<td><span class="vpn-status ' . strtolower(str_replace(' ', '-', $vpn_status)) . '">' . esc_html($vpn_status) . '</span></td>';
        echo '<td>' . $message . '</td>';
		echo '<td>' . $action . '</td>';
        echo '</tr>';
		$t_row = ob_get_contents();
		ob_end_clean();
		
   		$table_rows .= $t_row; 
	}
	
	ob_start();
	
	echo '<div class="summary_wrap">';
	echo '<div class="card total-order-amount">
      <h4>Total Order Amount</h4>
      <p>$</p>
    </div>
    <div class="card approved-unpaid_payout">
      <h4>Total Approved & Unpaid Payout USD</h4>
      <p>$</p>
    </div>
    <div class="card rolling_reserve">
      <h4>Rolling Reserve 10% (120 days)</h4>
      <p>$</p>
    </div>
    <div class="card final_payout">
      <h4>Final Payout Amount</h4>
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
	</style>';
	
	echo '<div class="fa-table-wrapp"><div id="filters" style="margin-bottom: 10px;">
  <label>From: <input type="date" id="min-date"></label>
  <label>To: <input type="date" id="max-date"></label>
</div><table class="table dt-responsive transactions-table" border="1" cellpadding="8" cellspacing="0">';
    
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
    echo '<th>Phone</th>';
    echo '<th>Order ID</th>';
	echo '<th>Order Amount USD</th>';
	echo '<th>Payout USD</th>';
	echo '<th>Rolling Reserve</th>';
    echo '<th class="all">Status</th>';
    echo '<th>User IP</th>';
    echo '<th>VPN/PROXY</th>';
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
					$payout += $t['amount'];
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
        dom: 'Blfrtip',
        buttons: ['csvHtml5'],
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
			{ targets: 13, orderable: false },
			{ targets: 5, orderable: false }
        ],
        initComplete: function () {
            // Dropdown filter for "Status" column (index 13)
            this.api().columns(13).every(function () {
                const column = this;
                const select = $('<select><option value="">All Status</option></select>')
                    .appendTo($(column.header()).empty())
                    .on('change', function () {
                        const val = $.fn.dataTable.util.escapeRegex($(this).val());
                        column.search(val ? '^' + val + '$' : '', true, false).draw();
                    });

                column.data().unique().sort().each(function (d) {
                    if (d) {
                        const text = $('<div>').html(d).text(); // strip HTML
                        select.append('<option value="' + text + '">' + text + '</option>');
                    }
                });
            });
			
			this.api().columns(1).every(function () {
                const column = this;
                const select = $('<select><option value="">All Brands</option></select>')
                    .appendTo($(column.header()).empty())
                    .on('change', function () {
                        const val = $.fn.dataTable.util.escapeRegex($(this).val());
                        column.search(val ? '^' + val + '$' : '', true, false).draw();
                    });

                column.data().unique().sort().each(function (d) {
                    if (d) {
                        const text = $('<div>').html(d).text(); // strip HTML
                        select.append('<option value="' + text + '">' + text + '</option>');
                    }
                });
            });
			
			this.api().columns(5).every(function () {
                const column = this;
                const select = $('<select><option value="">GEO</option></select>')
                    .appendTo($(column.header()).empty())
                    .on('change', function () {
                        const val = $.fn.dataTable.util.escapeRegex($(this).val());
                        column.search(val ? '^' + val + '$' : '', true, false).draw();
                    });

                column.data().unique().sort().each(function (d) {
                    if (d) {
                        const text = $('<div>').html(d).text(); // strip HTML
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
			.column(10, { search: 'applied' }) // only filtered rows
			.data()
			.reduce(function (sum, val) {
				// Remove any HTML and convert to float
				const num = parseFloat($('<div>').html(val).text().replace(/[^0-9.-]+/g, ''));
				return sum + (isNaN(num) ? 0 : num);
			}, 0);

		$('.summary_wrap .card.total-order-amount p').text('$' + total_order_amount_usd.toFixed(2));
		
		const total_approved_unpaid_payout = table
			.column(11, { search: 'applied' }) // only filtered rows
			.data()
			.reduce(function (sum, val) {
				// Remove any HTML and convert to float
				const num = parseFloat($('<div>').html(val).text().replace(/[^0-9.-]+/g, ''));
				return sum + (isNaN(num) ? 0 : num);
			}, 0);

		$('.summary_wrap .card.approved-unpaid_payout p').text('$' + total_approved_unpaid_payout.toFixed(2));
		
		const total_rolling_reserve = table
			.column(12, { search: 'applied' }) // only filtered rows
			.data()
			.reduce(function (sum, val) {
				// Remove any HTML and convert to float
				const num = parseFloat($('<div>').html(val).text().replace(/[^0-9.-]+/g, ''));
				return sum + (isNaN(num) ? 0 : num);
			}, 0);

		$('.summary_wrap .card.rolling_reserve p').text('$' + total_rolling_reserve.toFixed(2));
		
		const final_payout = total_approved_unpaid_payout.toFixed(2) - total_rolling_reserve.toFixed(2);
		$('.summary_wrap .card.final_payout p').text('$' + final_payout);
		
		
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
					$payout += $t['amount'];
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