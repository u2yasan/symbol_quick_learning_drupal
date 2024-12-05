console.log('loading link_namespace.js');

import { NODE, epochAdjustment, identifier, networkType, generationHash } from './env.js';
const SymbolSDK = await import('./bundle.web.js');
const PrivateKey = SymbolSDK.core.PrivateKey;
// console.log('PrivateKey from core:', PrivateKey);
const KeyPair = SymbolSDK.symbol.KeyPair;
const SymbolFacade = SymbolSDK.symbol.SymbolFacade;

const Address = SymbolSDK.symbol.Address;
const facade = new SymbolFacade(identifier);
// const RepositoryFactoryHttp = facade.RepositoryFactoryHttp;
function initialize() {
// alert('initialize');
  var inputElement = document.getElementById('edit-ownder-pvtkey');
  inputElement.addEventListener('blur', function() {
    var inputValue = inputElement.value;  // 入力された値を取得
    var addressDiv = document.getElementById('symbol-address');
    var hiddenField = document.getElementById('symbol-address-hidden'); // hidden フィールド
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
      if (hiddenField) {
        hiddenField.value = account_address;
      }
    }else{
        addressDiv.innerHTML = 'confirm private key'; 
        if (hiddenField) {
          hiddenField.value = ''; // 無効な場合は空にする
        }
    }
  });
}
initialize();

console.log('loaded link_namespace.js');
//  // https://blog.opening-line.jp/記事一覧/esmjavascript-moduleとブラウザでsymbol-sdk3を使う
//  // https://qiita.com/planethouki/items/1f77e30d7adea0acecf5
// console.log('loaded create_namespace.js');