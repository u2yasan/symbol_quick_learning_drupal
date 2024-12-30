console.log('loading list_metadata.js');

import { NODE, epochAdjustment, identifier, networkType, generationHash } from '/modules/contrib/quicklearning_symbol/js/test.env.js';
const SymbolSDK = await import('/modules/contrib/quicklearning_symbol/js/bundle.web.js');
// const SymbolSDK = (await import('/modules/contrib/quicklearning_symbol/js/bundle.web.js')).default;
// console.log('SymbolSDK:', SymbolSDK);
// console.log('Available keys in SymbolSDK:', Object.keys(SymbolSDK));
// Object.keys(SymbolSDK).forEach(key => console.log(key));
// console.log('SymbolSDK.core:', SymbolSDK.core());
// 
// (async () => {
//   const SymbolSDK = await import('/modules/contrib/quicklearning_symbol/js/bundle.web.js');
//   console.log('SymbolSDK:', SymbolSDK);
//   console.log('Available keys in SymbolSDK:', Object.keys(SymbolSDK));
//   console.log('SymbolSDK.core:', SymbolSDK.core);
// })();

const PrivateKey = SymbolSDK.core.PrivateKey;

// console.log('loading privatekey');
// console.log('PrivateKey from core:', PrivateKey);
const KeyPair = SymbolSDK.symbol.KeyPair;
const SymbolFacade = SymbolSDK.symbol.SymbolFacade;

const Address = SymbolSDK.symbol.Address;
const facade = new SymbolFacade(identifier);
// const RepositoryFactoryHttp = facade.RepositoryFactoryHttp;
function initialize() {
// alert('initialize');
  // var inputElement = document.getElementById('edit-source-pvtkey');
  // var inputElement = document.getElementById('edit-target-pvtkey'); 
  // 監視対象フィールドと対応するアドレス表示要素を定義
  const fields = [
    { inputId: 'edit-source-pvtkey', addressDivId: 'source-symbol-address' },
    // { inputId: 'edit-target-pvtkey', addressDivId: 'target-symbol-address' },
  ];
  // inputElement.addEventListener('blur', function() {
  //   var inputValue = inputElement.value;  // 入力された値を取得
  //   var addressDiv = document.getElementById('source-symbol-address');
  //   var addressDiv = document.getElementById('target-symbol-address');
  // 各フィールドにイベントリスナーを追加
  fields.forEach(({ inputId, addressDivId }) => {
    const inputElement = document.getElementById(inputId);
    const addressDiv = document.getElementById(addressDivId);
    if (inputElement && addressDiv) {
      inputElement.addEventListener('blur', function () {
        const inputValue = inputElement.value; // 入力値を取得
 
        if(inputValue.length=='64'){
          const privateKey = new PrivateKey(inputValue); 
          const keyPair = new KeyPair(privateKey);
          const publicKey = keyPair.publicKey; 
          const account_address = new Address(
            facade.network.publicKeyToAddress(publicKey)
          );
          addressDiv.innerHTML = account_address;
        }else{
          addressDiv.innerHTML = 'confirm private key'; 
        }
      });
    }else{
      console.warn(`Element with ID ${inputId} or ${addressDivId} not found`);
    }
  });
}
initialize();

console.log('loaded list_metadata.js');
