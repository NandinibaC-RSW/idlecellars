var appId = square_params.application_id;
var locationId= square_params.location_id;
console.log('Appid'+appId);
console.log('locationId'+locationId);

document.addEventListener("DOMContentLoaded", function(event) {    main();      });
   jQuery(document).ready(function() {
    jQuery("#addCard").click(function() {
        jQuery("#form-container").slideToggle("slow");
    });
});

async function main() {
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
          document.getElementById('payment-form').submit();
        }
      } catch (e) {
        console.error(e);
      }
    };

    const cardButton = document.getElementById('card-button');
    cardButton.addEventListener('click', eventHandler);
}

  