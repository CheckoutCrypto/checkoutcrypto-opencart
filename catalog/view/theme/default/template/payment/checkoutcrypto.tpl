<!--
Copyright (c) 2013 John Atkinson (jga)
*/
-->

<?php if(!$error) { ?>
	<div class="buttons">
		<div class="right"><a id="button-pay" class="button"><span><?php echo $button_checkoutcrypto_pay; ?></span></a></div>
	</div>
<?php } else { ?>
	<div class="warning">
		<?php echo $error_msg; ?>
	</div>
<?php } ?>
<script type="text/javascript"><!--
if (typeof colorbox == 'undefined') {
	var e = document.createElement('script');
	e.src = 'catalog/view/javascript/jquery/colorbox/jquery.colorbox.js';
	e.type = "text/javascript";
	document.getElementsByTagName("head")[0].appendChild(e);
	e = document.createElement('link');
	e.rel = "stylesheet";
	e.type = "text/css";
	e.href = "catalog/view/javascript/jquery/colorbox/colorbox.css";
	e.media = "screen"
	document.getElementsByTagName("head")[0].appendChild(e);
}
    e = document.createElement('link');
    e.rel = "stylesheet";
    e.type = "text/css";
    e.href = "catalog/view/theme/default/stylesheet/checkoutcrypto.css";
    e.media = "screen"
    document.getElementsByTagName("head")[0].appendChild(e);

var countdown;
clearInterval(countdown);
countdown = 0;

var timeleft = <?php echo $checkoutcrypto_countdown_timer; ?>;
var checker = 0;
var iVal = 5000;
var expired_countdown_content = '<div style="font-size:16px; padding:6px; text-align:center;"><?php echo $text_countdown_expired ?></div>';
function timer () {
	timeleft = timeleft -1;
	if(timeleft <= 0)
	{
		clearInterval(countdown);
		countdown = 0;
		document.getElementById("cboxLoadedContent").innerHTML = expired_countdown_content;
		clearInterval(checker);
		checker = 0;
	}
	var minutes = Math.floor(timeleft/60);
	var seconds = timeleft%60;
	var seconds_string = "0" + seconds;
	seconds_string = seconds_string.substr(seconds_string.length - 2)
	if(document.getElementById("timer") != null){
		document.getElementById("timer").innerHTML = minutes + ":" + seconds_string;
	}else{
		timeleft = 0;
	}
}

$('#button-pay').on('click', function() {
 $.ajax({ 
	type: 'GET',
	url: 'index.php?route=payment/checkoutcrypto/order_coins_display',
	timeout: 5000,
	dataType: 'text',
	error: function() {
		document.getElementById("cboxLoadedContent").innerHTML += '<div class="warning"><?php echo $error_confirm; ?></div>';
	},
	success: function(received) {
		var arr = $.parseJSON(received);
		if(timeleft > 0) {
		    html = '<div id="cc-wrapper">';
		    html += '<div id="cc-border">';
		    html += '<div id="cc_pay"></div>';
		    html += '<div id="oc-cc-payment-info">Please select your preferred cryptocurrency to continue with payment</div>';
		    html += '<div id="cc_coin_select_wrapper" class="oc_cc_show">';
			for (var i in arr) 
			{
				html +=" <div class='cc_coin' id='cc_coin_" + arr[i].coin_code.toLowerCase() + "' style='background: url("+arr[i].coin_img+" ); height: 150px; width: 150px; left no-repeat;'> </div>";
			}
			html +=  '</div>';
		    html += '<div id="cc_payment_processing_wrapper" class="oc_cc_hide"><input name="oc_cc_selected_coin" value="" type="hidden">';
		    html += '<div id="oc_cc_payment_address_container">';
		    html += '<div class="form-item form-type-textfield form-item-oc-cc-payment-address">';
		    html += '<input readonly="readonly" size="50" id="oc-cc-payment-address" name="oc_cc_payment_address" value="" maxlength="128" class="form-text" type="text">';
		    html += '</div></div>';
		    html += '<div id="oc_cc_payment_qr_address_container"></div>';
		    html += '<div class="center"><?php echo $text_pre_timer ?><span id="timer" style="font-weight: bold;"></span><?php echo $text_post_timer ?></div>';
		    html += '<div id="cc_progress_status">This window will auto-refresh status until order is complete</div></div></div></div>';
		}
		else {
			html  = expired_countdown_content;
		}


		$.colorbox({
			overlayClose: true,
			opacity: 0.5,
			width: '650px',
			height: '375px',
			href: false,
			html: html,
			onComplete: function() {
		         $("body").click(function(e) {
		            if($(e.target).parent().is('#cc_coin_select_wrapper')) {
		                console.log(e.target.id);
		                var coin_code = e.target.id;
		                coin_code = coin_code.substring(8);
                    	if(coin_code == "btc"){
                              iVal = iVal *2;
                              timeleft = timeleft *2;
                        }
		                checkoutcrypto_order_details(coin_code);
		            } else {
		                console.log("nope");
		            }
		        });
		        
		        $('#button-confirm').on('click', function() {
		            $.ajax({ 
		                type: 'GET',
		                url: 'index.php?route=payment/checkoutcrypto/confirm_sent',
		                timeout: 5000,
		                dataType: 'text',
		                error: function() {
		                    document.getElementById("cboxLoadedContent").innerHTML += '<div class="warning"><?php echo $error_confirm; ?></div>';
		                },
		                success: function(received) {
		                    if(received != "1") {
		                        document.getElementById("cboxLoadedContent").innerHTML += '<div class="warning"><?php echo $error_incomplete_pay; ?></div>';
		                    }
		                    else {
		                        location.href = 'index.php?route=checkout/success';
		                    }
		                }
		            });
		        });

				$('#button-confirm').on('click', function() {
					$.ajax({ 
						type: 'GET',
						url: 'index.php?route=payment/checkoutcrypto/confirm_sent',
						timeout: 5000,
						dataType: 'text',
						error: function() {
							document.getElementById("cboxLoadedContent").innerHTML += '<div class="warning"><?php echo $error_confirm; ?></div>';
						},
						success: function(received) {
							if(received != "1") {
								document.getElementById("cboxLoadedContent").innerHTML += '<div class="warning"><?php echo $error_incomplete_pay; ?></div>';
							}
							else {
								location.href = 'index.php?route=checkout/success';
							}
						}
					});
				});
				function checkoutcrypto_check () {
					if(timeleft > 0) {
						$.ajax({ 
							type: 'GET',
							url: 'index.php?route=payment/checkoutcrypto/confirm_sent',
							timeout: 5000,
							dataType: 'text',
							error: function() {
								document.getElementById("cboxLoadedContent").innerHTML += '<div class="warning"><?php echo $error_confirm; ?></div>';
							},
							success: function(received) {
								if(received == "1") {
									location.href = 'index.php?route=checkout/success';
								}
							}
						});
					}
				}
		        function checkoutcrypto_order_coins() {
		            if(timeleft > 0) {
		                $.ajax({ 
		                    type: 'GET',
		                    url: 'index.php?route=payment/checkoutcrypto/order_coins',
		                    timeout: 5000,
		                    dataType: 'text',
		                    error: function() {
		                        document.getElementById("cboxLoadedContent").innerHTML += '<div class="warning"><?php echo $error_confirm; ?></div>';
		                    },
		                    success: function(received) {
		                    }
		                });
		            }
		        }
		        function checkoutcrypto_order_details(coin_code) {
		            if(timeleft > 0) {
		                $.ajax({ 
		                    type: 'POST',
		                    url: 'index.php?route=payment/checkoutcrypto/order_details',
		                    timeout: 5000,
		                    dataType: 'text',
		                    data: {
		                        coin_code: coin_code,
		                    },
		                    error: function() {
		                        document.getElementById("cboxLoadedContent").innerHTML += '<div class="warning"><?php echo $error_confirm; ?></div>';
		                    },
		                    success: function(received) {
		                        console.log(received);
		                        response = JSON.parse(received);
		                        if(response['status'] == 'success') {
		                             $('#cc_payment_processing_wrapper').show();
		                             $("#cc_payment_processing_wrapper").fadeTo("slow", 1.00, function(){ //fade and toggle class
		                                 $(this).slideDown("slow");
		                                 $(this).toggleClass("oc_cc_hidden");
		                             });

		                             $("#cc_coin_select_wrapper").fadeTo("slow", 0.00, function(){ //fade and toggle class
		                                 $(this).slideUp("slow");
		                                 $(this).toggleClass("oc_cc_hidden");
		                            });
		                            $('#cc_coin_reselect').show();

		                             var url_qr_base = 'https://chart.googleapis.com/chart?cht=qr';
		                             var url_qr_args = '&chs=150';
		                             url_qr_args += '&choe=UTF8';
		                             url_qr_args += '&chld=L';
		                             url_qr_args += "&chl="+response['coin_address'];
		                             var url_qr = url_qr_base+url_qr_args;
		                             var url_qr_output = '<img src="'+url_qr+'">';

		                             document.getElementById("oc-cc-payment-info").innerHTML = '<?php echo $text_please_send ?> <span style="font-weight: bold;"> '+response['coin_amount']+'</span> '+coin_code.toUpperCase()+' to:';
		                             document.getElementById("oc-cc-payment-address").value = response['coin_address'];
		                             document.getElementById("oc_cc_payment_qr_address_container").innerHTML = url_qr_output;
		                             countdown = setInterval(timer, 1000);
		                             checker = setInterval(checkoutcrypto_check, iVal);
		                        }
		                    }
		                });
		            }
		        }
			},
			onCleanup: function() {
				clearInterval(checker);
				checker = 0;
			}
		});
	}
});
}); 
//--></script> 
