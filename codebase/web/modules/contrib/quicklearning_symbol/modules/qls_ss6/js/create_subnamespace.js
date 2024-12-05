console.log('loading create_subnamespace.js');

const SymbolSDK = await import('./bundle.web.js');
const PrivateKey = SymbolSDK.core.PrivateKey;
const KeyPair = SymbolSDK.symbol.KeyPair;
const SymbolFacade = SymbolSDK.symbol.SymbolFacade;

const Address = SymbolSDK.symbol.Address;

const facade = new SymbolFacade(identifier);

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



function initialize() {
  // alert('initialize');
  var inputElement = document.getElementById('edit-ownder-pvtkey');
  inputElement.addEventListener('blur', function() {
    var inputValue = inputElement.value;  // 入力された値を取得
    var addressDiv = document.getElementById('symbol_address');
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
      if (addressDiv) {
        addressDiv.innerHTML = 'confirm private key';
      }
      if (hiddenField) {
          hiddenField.value = ''; // 無効な場合は空にする
      }
    }
  });

}
initialize();

console.log('loaded create_subnamespace.js');
