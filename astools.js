// Using NAMI: https://github.com/marcelog/Nami/

var namiConfig = {
  host: "localhost",
  port: 5038,
  username: "admin",
  secret: "secret5"
};

var logger = require('log4js').getLogger('App.astools');
var nami = new (require("nami").Nami)(namiConfig);
var amiConnected = false;
/*
nami.on('namiEvent', function (event) { });
nami.on('namiEventDial', function (event) { });
nami.on('namiEventVarSet', function (event) { });
nami.on('namiEventHangup', function (event) { });
process.on('SIGINT', function () {
  nami.close();
  process.exit();
});
*/
nami.on('namiConnected', function (event) {
  //nami.send(new namiLib.Actions.CoreShowChannelsAction(), function(response){
  //  logger.debug(' ---- Response: ' + util.inspect(response));
  //});
  amiConnected = true;
});
nami.open();

/*
 'action':'originate',
 'channel':'SIP/myphone',
 'context':'default',
 'exten':1234,
 'priority':1,
 'variables':{
   'name1':'value1',
   'name2':'value2'
 }
*/
function asCallOriginate(data) {
  var to_number = data["to_number"];
  var connect_extn = data["connect_extn"];
  var record_call = data["record_call"];
  logger.info("New call to " + to_number + " from " + connect_extn + " call_record " + record_call);

  var action = new namiLib.Actions.Status();
  action.variables = {
    'action': 'originate',
    'channel':'SIP/' + to_number,
    'context':'click2call',
    'exten': connect_extn,
    'priority':1,
    'variables':{
      'call_record':record_call,
      'message':'',
      'tripid':0
    }
  };
  nami.send(action, function(response) {
    logger.debug("Call originated: " + response);
  });
}

function makeNewCall(data) {
  if (amiConnected) {
    asCallOriginate(data);
  }
  return amiConnected;
}

module.exports.makeNewCall = makeNewCall;
