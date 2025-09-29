function evenCheck(n) {

  let num = n / 2;

  if (Math.floor(num) === Math.ceil(num)){
    return true;
  } else {
    return false;
  }
}

evenCheck(4);
evenCheck(5);