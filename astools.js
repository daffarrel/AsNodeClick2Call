// Using NAMI: https://github.com/marcelog/Nami/
var namiLib = require("nami");

var namiConfig = {
  host: "localhost",
  port: 5038,
  username: "admin",
  secret: "secret5"
};

var logger = require('log4js').getLogger('App.astools');
var nami = new namiLib.Nami(namiConfig);
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
  var message = data["message"];
  var tripid = data["tripid"];

  message = message.replace(new RegExp(',', 'g'), ' ');;
  logger.info("New call to " + to_number + " from " + connect_extn + " call_record " + record_call);

  var action = new namiLib.Actions.Originate();
  action.Channel = 'SIP/' + to_number;
  action.Context = "moveivr-click2call";
  action.Exten = connect_extn;
  action.Priority = 1;
  action.CallerID = "MoveIVR Caller";
  action.variables = {
    'TO-NUMBER': to_number,
    'CONNECT-EXT': connect_extn ,
    'CALL-RECORD':record_call,
    'MESSAGE': message,
    'TRIPID': tripid
  };
  standardSend(action);
}

function standardSend(action) {
  nami.send(action, function (response) {
    logger.debug(' ---- Response: ' + util.inspect(response));
  });
}

function makeNewCall(data) {
  if (amiConnected) {
    asCallOriginate(data);
  }
  return amiConnected;
}

module.exports.makeNewCall = makeNewCall;
