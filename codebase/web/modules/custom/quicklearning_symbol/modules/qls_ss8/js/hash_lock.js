console.log('loading hash_lock.js');

// import { NODE, epochAdjustment, identifier, networkType, generationHash } from '../../../js/test.env.js';
import { NODE, epochAdjustment, identifier, networkType, generationHash } from '/modules/contrib/quicklearning_symbol/js/test.env.js';
const SymbolSDK = await import('../../../js/bundle.web.js');

// const SymbolSDK = (await import('/modules/contrib/quicklearning_symbol/js/bundle.web.js')).default;
console.log('SymbolSDK:', SymbolSDK);

// const SymbolSDK = await import('../../../js/bundle.web.js');
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

function initialize() {
  // alert('initialize');
  var inputElement = document.getElementById('edit-originator-pvtkey');
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
}
initialize();

console.log('loaded hash_lock.js');
