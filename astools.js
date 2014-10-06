// Using NAMI: https://github.com/marcelog/Nami/
var namiLib = require("nami");
var child_process = require('child_process');

var namiConfig = {
  host: "localhost",
  port: 5038,
  //username: "admin",
  //secret: "secret5"
  username: "local_mgr",
  secret: "RpfpkKOYLilLwUSAqgSz"
};

var SIPPROVIDER = "FonalityVoIP";

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

// reload asterisk server to apply new config
function reloadAS() {
  var cmd = "asterisk -rx 'moh reload'";
  child_process.exec(cmd, function (err, data) {
    if (err)
      logger.info("reload AS MOH failed!");
    else
      logger.info("reload AS MOH successed!");
  });
}

// create new class for MOH
function newMOHClass(folder, filename) {
  var mode = "files";
  var fs = require('fs');
  var filepath = "./asterisk/config/moveivr_click2call_moh.conf";
  var data = "[" + filename + "]\n" + "mode=" + mode + "\n" + "directory=" + folder + "\n";

  if (fs.existsSync(filepath)) { 
    fs.appendFile(filepath, data + "\n", function (err) {
      //reloadAS();
    });
  }
  else {
    fs.writeFile(filepath, data + "\n", function (err) {
      //reloadAS();
    }); 
  } 

  logger.info("newMOHClass done, data added");
}

// convert text to speech 
function text2speech(filepath, text) {
  var cmd = "/usr/local/bin/swift  -o " + filepath + " -p audio/channels=1,audio/sampling-rate=8000 '" + text + "'";
  logger.info("text2speech cmd: " + cmd)
  child_process.exec(cmd, function (err, data) {
    logger.info("text2speech done");
    reloadAS();
  });
}

// setup moh for current channel
function mohSetup(filename, text) {
  var folder = "/tmp/" + filename;
  var cmd = "mkdir " + folder;
  child_process.exec(cmd, function (err, data) {
  });
  var filepath = folder + "/" + filename + ".wav";
  text2speech(filepath, text);
  newMOHClass(folder, filename);
}

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
  
TEST: curl --header "X-API-KEY: dfalkehasdhf2349238dfskhfk2" -d "to_number=84979820611&connect_extn=84987332282&record_call=N&message=how are you today&tripid=10" http://216.14.95.50:3000/click2call
curl -d "to_number=84979820611&connect_extn=84987332282&record_call=N&message=how are you today&tripid=10" http://65.60.43.99:3000/click2call
*/
function asCallOriginate(data, record_filename = '') {
  var to_number = data["to_number"];
  var connect_extn = data["connect_extn"];
  var record_call = data["record_call"];
  var message = data["message"];
  var tripid = data["tripid"];
  var calloptions = "g";
  var filename = '';

  if (message.length > 0) {
    //message = message.replace(new RegExp(',', 'g'), ' ');
    var unix = Math.round(+new Date()/1000);
    filename = to_number + "_" + connect_extn + "_" + unix;
    mohSetup(filename, message);
    calloptions += "m";
  }

  logger.info("New call to " + to_number + " from " + connect_extn + " call_record " + record_call);

  var action = new namiLib.Actions.Originate();
  action.Channel = 'SIP/' + to_number + "@" + SIPPROVIDER;
  action.Context = "moveivr-click2call";
  action.Exten = connect_extn;
  action.Priority = 1;
  action.CallerID = "MoveIVR Caller";
  action.variables = {
    'TO-NUMBER': to_number,
    'CONNECT-EXT': connect_extn ,
    'CALL-RECORD':record_call,
    'RECORD-NAME': record_filename,
    'MOHCUSTOM': filename,
    'DIALOPTIONS': calloptions,
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
