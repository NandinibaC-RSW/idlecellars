jQuery(document).ready(function ($) {
  $("#submit").on("click", function(){
      // alert('click');
    });

      $('<a href="#addCard" class="button button-primary button-add-card">ADD CARD</a>').appendTo('#wc_payment_gateway_square_user_settings h3');
      $('<input type="hidden" id="payment_token" name="payment_token">').appendTo('form#your-profile #wc_payment_gateway_square_user_settings');
  

      $('<h3 class="form-table-white-heading add-card-form" id="addCard">Square add credit card</h3>\n' +

        '<div class="add-card-form">'+
       '<form id="payment-form" method="POST">' +
          '<input type="hidden" name="payment_token" id="payment_token" value="">' +
          '<div id="card-container"></div>' +
         ' <button id="card-button" type="button"  class="button-credit-card button button-primary">ADD CARD</button>'+
        '</form>'+
        '</div>'+

        '\n' +
        
        '<div id="sq-walletbox" style="display: none;">\n' +
        '    Pay with a Digital Wallet\n' +
        '    <div id="sq-apple-pay-label" class="wallet-not-enabled">Apple Pay for Web not enabled</div>\n' +
        '    <!-- Placeholder for Apple Pay for Web button -->\n' +
        '    <button id="sq-apple-pay" class="button-apple-pay"></button>\n' +
        '\n' +
        '    <div id="sq-masterpass-label" class="wallet-not-enabled">Masterpass not enabled</div>\n' +
        '    <!-- Placeholder for Masterpass button -->\n' +
        '    <button id="sq-masterpass" class="button-masterpass "></button>\n' +
        '</div>').appendTo('#profile-page');
        
        main(); // call function
    });


async function main() {
    var appId = square_params.application_id;
    var locationId= square_params.location_id;
    
    const payments = Square.payments(appId, locationId);
	
    const card = await payments.card();
    await card.attach('#card-container');

    async function eventHandler(event) {
    event.preventDefault();

      try {
        const result = await card.tokenize();
        if (result.status === 'OK') {
          // console.log(`Payment token is ${result.token}`);
          document.getElementById('payment_token').value = result.token;
		  console.log(result.token);
		  // cnon:CBESEHVD0TJBcCbrWwSXthOdyEQ
          // document.getElementById('payment-form').submit();
          document.getElementById('submit').click();
        }
      } catch (e) {
        console.error(e);
      }
    };

    const cardButton = document.getElementById('card-button');
    cardButton.addEventListener('click', eventHandler);
}
