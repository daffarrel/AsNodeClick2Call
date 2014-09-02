var express = require("express");
var path = require('path');
var http = require('http');
var bodyParser = require("body-parser");

// prepare a simple HTTP server
var app = express();
var server = http.createServer(app);

var SECRET_KEY = "dfalkehasdhf2349238dfskhfk2";

// init Asterisk AMI https://github.com/pipobscure/NodeJS-AsteriskManager
//var ami = new require('asterisk-manager')('5038','localhost','username','password', true);

server.listen(3000);
console.log("Asterisk Click2call - http://0.0.0.0:3000 - Server Started");

app.use(bodyParser.urlencoded({ extended: false}));
//app.use(bodyParser.json());

app.all('*', function(req, res, next) {
  res.header("Access-Control-Allow-Origin", "*");
  res.header('Access-Control-Allow-Methods', 'GET,PUT,POST,DELETE');
  next();
});

app.get('/', function(req, res){
	res.send('Hello world!');
});


/*
 Payload:
  to_number*: nnnnnnnnnn (PSTN number)
  connect_extn*: nnnn  (SIP extention number)
  record_call*: Y|N
  message: (Text message to be sent during the callout) (optional parameter)
  tripid: (Tripid to record the transaction and the call) (optional parameter)
 */
app.post('/click2call', function(req, res){
  res.statusCode = 200;
  var res_message = "Your request will be processed now!";
  var res_status = "Success";

  // data init
  var to_number = "";
  var connect_extn = "";
  var record_call = 'N';
  var message = "";
  var tripid = 0;

  if (!req.headers.hasOwnProperty("x-api-key") || req.headers["x-api-key"] != SECRET_KEY){
    res.statusCode = 404;
    res_message = "You can not access to this area!";
    res_status = "Forbidden";
  }

  if (!req.body.hasOwnProperty("to_number")) {
    res.statusCode = 400;
    res_message = "to_number is required!";
    res_status = "Error";
  }
  else if (!req.body.hasOwnProperty("connect_extn")) {
    res.statusCode = 400;
    res_message = "connect_extn is required!";
    res_status = "Error";
  }
  else if (!req.body.hasOwnProperty("record_call")) {
    res.statusCode = 400;
    res_message = "record_call is required!";
    res_status = "Error";
  }
  else{
    makeNewCall(req.body, function(){});
  }

  res.jsonp(JSON.stringify({ status: res_status, message: res_message }));
  res.end();
});

var hasOwnProperty = Object.prototype.hasOwnProperty;
var isEmpty = function(obj){
  if (obj === null) return true;

  if (obj.length > 0) return false;
  if (obj.length === 0) return true;

  for (var key in obj)	{
    if (hasOwnPropoerty.call(obj, key)) return false;
  }

  return true;
};

var makeNewCall = function(data, callback) {
  to_number = data["to_number"];
  connect_extn = data["connect_extn"];
  record_call = data["record_call"];
  console.log("New call to " + to_number + " from " + connect_extn + " is_record " + record_call);
};