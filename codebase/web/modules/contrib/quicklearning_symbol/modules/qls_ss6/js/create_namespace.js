console.log('loading create_namespace.js');

const SymbolSDK = await import('./bundle.web.js');
// console.log('Core Keys:', Object.keys(SymbolSDK.core || {}));
// console.log('Symbol Keys:', Object.keys(SymbolSDK.symbol || {}));
// Object.keys(SymbolSDK.symbol || {}).forEach((key) => {
//   const value = SymbolSDK.symbol[key];
//   if (value) {
//     console.log(`Methods and Properties of ${key}:`, Object.getOwnPropertyNames(value.prototype || value));
//   } else {
//     console.log(`${key} is not accessible or undefined.`);
//   }
// });
// console.log('Symbol SymbolTransactionFactory Keys:', Object.keys(SymbolSDK.symbol.SymbolTransactionFactory || {})); 
const PrivateKey = SymbolSDK.core.PrivateKey;
// console.log('PrivateKey from core:', PrivateKey);
const KeyPair = SymbolSDK.symbol.KeyPair;
const SymbolFacade = SymbolSDK.symbol.SymbolFacade;

const Address = SymbolSDK.symbol.Address;

const facade = new SymbolFacade(identifier);
// const RepositoryFactoryHttp = facade.RepositoryFactoryHttp;



import { NODE, epochAdjustment, identifier, networkType, generationHash } from './env.js';

async function fetchRentalFees() {
  try {
    const response = await fetch(`${NODE}/network/fees/rental`);
    if (!response.ok) throw new Error(`HTTP Error: ${response.status}`);
    const rentalFees = await response.json();
    if (!rentalFees || !rentalFees.effectiveRootNamespaceRentalFeePerBlock) {
      throw new Error('Invalid rental fees data');
    }
    return rentalFees;
  } catch (error) {
    console.error('Error fetching rental fees:', error);
    throw error; // エラーを再スローして上位で処理
  }
}



// (function (Drupal, once) {
//   const initialize = (element) => {
//     console.log('initialize function called for:', element);
//     element.addEventListener('blur', () => {
//       const inputValue = element.value;
//       const addressDiv = document.getElementById('symbol_address');
//       if (inputValue.length === 64) {
//         const privateKey = new PrivateKey(inputValue);
//         const keyPair = new KeyPair(privateKey);
//         const publicKey = keyPair.publicKey;
//         const account_address = new Address(
//           facade.network.publicKeyToAddress(publicKey)
//         );
//         if (addressDiv) {
//           addressDiv.innerHTML = account_address;
//         }
//       } else {
//         if (addressDiv) {
//           addressDiv.innerHTML = 'confirm private key';
//         }
//       }
//     });
//   };

//   Drupal.behaviors.createNamespace = {
//     attach: function (context, settings) {
//       console.log('Drupal.behaviors.attach called');
      
//       const elements = once('createNamespace', '#edit-ownder-pvtkey', context);
//       console.log(`Found elements: ${elements.length}`);
      
//       elements.forEach((element) => {
//         initialize(element);
//       });
//     },
//   };
// })(Drupal, once);








// (function($) {
//   // Argument passed from InvokeCommand.
//   $.fn.simpleTransferCallback = async function(pvtkey, toAddress, mosaic_id, mosaic_amount) {

//     console.log("Function called with arguments: " + mosaic_amount);
//     // // Set textfield's value to the passed arguments

//     const privateKey = new PrivateKey(pvtkey); 
//     const keyPair = new KeyPair(privateKey);
//     const publicKey = keyPair.publicKey;

//     const mosaic_id_hex = BigInt(mosaic_id);
//     const mosaic_amount_biginit = BigInt(mosaic_amount);
//     // 転送モザイク設定
//     const sendMosaics = [
//       // { mosaicId: CURRENCY_MOSAIC_ID, amount: 5_000000n },
//       { mosaicId: mosaic_id_hex, amount: mosaic_amount_biginit } 
//     ];

//     const tx = facade.transactionFactory.create({
//       type: "transfer_transaction_v1", // Txタイプ:転送Tx
//       signerPublicKey: publicKey, // 署名者公開鍵
//       deadline: facade.now().addHours(2).timestamp,
//       recipientAddress: toAddress,
//       mosaics: sendMosaics,
//     });
//     tx.fee = new models.Amount(BigInt(tx.size * 100)); //手数料

//     const sig = facade.signTransaction(keyPair, tx);
//     const jsonPayload = facade.transactionFactory.static.attachSignature(tx, sig);
//     const hash = facade.hashTransaction(tx).toString();
//     // console.log(jsonPayload);
//     // console.log(hash);

//     // RESTにアナウンス
//     const transactionsResponse = await fetch(
//       new URL('/transactions', NODE),
//       {
//         method: 'PUT',
//         headers: { 'Content-Type': 'application/json' },
//         body: jsonPayload,
//       }
//     );
//     // // アナウンス結果を表示
//     const transactionsResponseJson = await transactionsResponse.json();
//     console.log(`restResponse      : ${transactionsResponseJson.message}`);

//     $('#box-container').html(`${transactionsResponseJson.message}`);
//     // $('#box-container').html(`${arg1}`);


//     // const response = await fetch(
//     //   new URL('/transactions', nodeUrl),
//     //   {
//     //     method: 'PUT',
//     //     headers: { 'Content-Type': 'application/json' },
//     //     body: jsonPayload
//     //   })
//     //   .then((res) => res.json());
    
//     // console.log(JSON.stringify(response));

//   };
// })(jQuery);

function initialize() {
  // alert('initialize');
  var inputElement = document.getElementById('edit-ownder-pvtkey');
  inputElement.addEventListener('blur', function() {
    var inputValue = inputElement.value;  // 入力された値を取得
    var addressDiv = document.getElementById('symbol_address');
    if(inputValue.length=='64'){
      const privateKey = new PrivateKey(inputValue); 
      const keyPair = new KeyPair(privateKey);
      const publicKey = keyPair.publicKey; 
      const account_address = new Address(
        facade.network.publicKeyToAddress(publicKey)
      );
      if (addressDiv) {
        addressDiv.innerHTML = account_address;
      }
    }else{
        addressDiv.innerHTML = 'confirm private key'; 
    }
  });

  // Duration フィールドの処理
  var durationElement = document.getElementById('edit-duration');
  if (durationElement) {
    durationElement.addEventListener('blur', function () {
      var inputValue = durationElement.value; // 入力された値を取得
      var durationDiv = document.getElementById('estimated_period_of_validity');
      var estimatedRentalFeeDiv = document.getElementById('estimated_rental_fee');
      if (durationDiv) {
        const rentalBlock = parseInt(inputValue, 10); // 入力値を整数に変換
        if (!isNaN(rentalBlock) && rentalBlock >= 86400 && rentalBlock <= 5256000) {
          const totalSeconds = rentalBlock * 30; // Duration rentalBlock x 30秒
          const days = Math.floor(totalSeconds / 86400); // 1日 = 86400秒
          const hours = Math.floor((totalSeconds % 86400) / 3600); // 1時間 = 3600秒
          const minutes = Math.floor((totalSeconds % 3600) / 60); // 1分 = 60秒

          // 表示を更新
          durationDiv.innerHTML = `${days}d ${hours}h ${minutes}m`;

          (async() =>{
            const rentalFees = await fetchRentalFees(); // 結果を待機
            // console.log('Rental Fees:', rentalFees);
           
            const rootNamespaceFeePerBlock = parseInt(rentalFees.effectiveRootNamespaceRentalFeePerBlock, 10);
    // console.log('Effective Root Namespace Rental Fee Per Block:', rootNamespaceFeePerBlock);
            const rootNsRenatalFeeTotal = rentalBlock * rootNamespaceFeePerBlock;
            // console.log("rentalBlock:" + rentalBlock);
            // console.log("rootNsRenatalFeeTotal:" + rootNsRenatalFeeTotal);
            const rootNSRFTXYM = (rootNsRenatalFeeTotal / 1000000).toFixed(2); 
            estimatedRentalFeeDiv.innerHTML = `${rootNSRFTXYM} XYM`;

          })();

          

        } else {
          durationDiv.innerHTML = 'Invalid duration. Please enter a value between 86400 and 5256000.';
        }
      }
    });
  } else {
    console.error('duration field not found');
  }
}
initialize();

console.log('loaded create_namespace.js');
//  // https://blog.opening-line.jp/記事一覧/esmjavascript-moduleとブラウザでsymbol-sdk3を使う
//  // https://qiita.com/planethouki/items/1f77e30d7adea0acecf5
// console.log('loaded create_namespace.js');