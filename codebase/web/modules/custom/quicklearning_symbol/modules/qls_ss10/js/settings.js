console.log('loading settings.js');

// import { NODE, epochAdjustment, identifier, networkType, generationHash } from '../../../js/test.env.js';
const SymbolSDK = await import('../../../js/bundle.web.js');

const PrivateKey = SymbolSDK.core.PrivateKey;
const KeyPair = SymbolSDK.symbol.KeyPair;
const SymbolFacade = SymbolSDK.symbol.SymbolFacade;
const Address = SymbolSDK.symbol.Address;


async function initialize() {
  var networkType = document.querySelector('input[name="network_type"]:checked');
  // alert(networkType);
  let env;
  
  if (networkType.value === 'mainnet') {
    env = await import('/modules/contrib/quicklearning_symbol/js/main.env.js');
  } else if (networkType.value === 'testnet') {
    env = await import('/modules/contrib/quicklearning_symbol/js/test.env.js');
  } else {
    throw new Error('Invalid network type');
  }

  // 使用例
  const { NODE, epochAdjustment, identifier, generationHash } = env;
  const facade = new SymbolFacade(identifier);


  var inputElement = document.getElementById('edit-multisig-pvtKey');
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

console.log('loaded settings.js');
